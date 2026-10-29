<?php

use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\InvalidOrderException;
use App\Models\Order;
use App\Models\Position;
use App\Models\User;
use App\Services\MarketData\FakeMarketDataProvider;
use App\Services\OrderService;
use App\Services\PortfolioService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->market = app(FakeMarketDataProvider::class)->freeze();
    $this->market->setPrice('AAPL', 10000); // $100,00
    $this->user = User::factory()->create(['starting_cash_cents' => 1_000_000, 'cash_cents' => 1_000_000]); // $10.000
    $this->orders = app(OrderService::class);
    $this->portfolio = app(PortfolioService::class);
    config(['trading.commission_cents' => 0, 'trading.slippage_bps' => 0]);
});

function placeOrder(OrderSide $side, OrderType $type, int $qty, array $extra = []): Order
{
    return test()->orders->place(test()->user, new PlaceOrderData(...[
        'symbol' => 'AAPL',
        'side' => $side,
        'type' => $type,
        'quantity' => $qty,
        ...$extra,
    ]));
}

function position(): ?Position
{
    return test()->portfolio->positionFor(test()->user, 'AAPL');
}

function price(int $cents): void
{
    test()->market->setPrice('AAPL', $cents);
    test()->orders->processOpenOrders();
}

describe('market orders', function () {
    it('fills a market buy immediately and updates cash and position', function () {
        $order = placeOrder(OrderSide::Buy, OrderType::Market, 10);

        expect($order->status)->toBe(OrderStatus::Filled)
            ->and($order->fill_price_cents)->toBe(10000)
            ->and($this->user->refresh()->cash_cents)->toBe(900_000)
            ->and(position()->quantity)->toBe(10)
            ->and(position()->cost_basis_cents)->toBe(100_000)
            ->and($this->user->transactions()->count())->toBe(1);
    });

    it('computes the average entry price over several buys', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $this->market->setPrice('AAPL', 12000);
        placeOrder(OrderSide::Buy, OrderType::Market, 10);

        expect(position()->quantity)->toBe(20)
            ->and(position()->averagePriceCents())->toBe(11000);
    });

    it('books realized pnl on a partial and full sale', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $this->market->setPrice('AAPL', 11000);
        placeOrder(OrderSide::Sell, OrderType::Market, 4);

        $first = $this->user->transactions()->latest('id')->first();
        expect($first->realized_pnl_cents)->toBe(4_000)
            ->and(position()->quantity)->toBe(6)
            ->and(position()->cost_basis_cents)->toBe(60_000);

        placeOrder(OrderSide::Sell, OrderType::Market, 6);

        expect(position())->toBeNull()
            ->and($this->user->refresh()->cash_cents)->toBe(1_010_000)
            ->and($this->user->transactions()->sum('realized_pnl_cents'))->toBe(10_000);
    });

    it('charges commission and slippage', function () {
        config(['trading.commission_cents' => 100, 'trading.slippage_bps' => 10]); // 0,10%

        $order = placeOrder(OrderSide::Buy, OrderType::Market, 10);

        expect($order->fill_price_cents)->toBe(10010)
            ->and($order->commission_cents)->toBe(100)
            ->and($this->user->refresh()->cash_cents)->toBe(1_000_000 - 100_100 - 100)
            ->and(position()->cost_basis_cents)->toBe(100_200);
    });

    it('keeps equity consistent with realized plus unrealized pnl', function () {
        config(['trading.commission_cents' => 100]);
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $this->market->setPrice('AAPL', 11000);
        placeOrder(OrderSide::Sell, OrderType::Market, 5);
        $this->market->setPrice('AAPL', 10500);

        $summary = $this->portfolio->summary($this->user);
        $realized = $this->user->transactions()->sum('realized_pnl_cents');

        expect($summary->equityCents - 1_000_000)->toBe($realized + $summary->unrealizedPnlCents)
            ->and($summary->totalPnlCents)->toBe($summary->equityCents - 1_000_000);
    });
});

describe('validation', function () {
    it('rejects buying more than the buying power', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 101);
    })->throws(InvalidOrderException::class, 'Onvoldoende koopkracht');

    it('rejects selling shares that are not owned', function () {
        placeOrder(OrderSide::Sell, OrderType::Market, 1);
    })->throws(InvalidOrderException::class, 'short');

    it('rejects selling more than owned', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 5);
        placeOrder(OrderSide::Sell, OrderType::Market, 6);
    })->throws(InvalidOrderException::class);

    it('requires a limit price for limit orders', function () {
        placeOrder(OrderSide::Buy, OrderType::Limit, 1);
    })->throws(InvalidOrderException::class, 'limietprijs');

    it('rejects a short while holding a long position', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 5);
        placeOrder(OrderSide::Sell, OrderType::Market, 1, ['isShort' => true]);
    })->throws(InvalidOrderException::class, 'long-positie');

    it('rejects an invalid symbol', function () {
        $this->orders->place($this->user, new PlaceOrderData('NOT A TICKER', OrderSide::Buy, OrderType::Market, 1));
    })->throws(InvalidOrderException::class, 'geen geldig');
});

describe('limit, stop and stop-limit orders', function () {
    it('fills a buy limit only when the price drops to the limit', function () {
        $order = placeOrder(OrderSide::Buy, OrderType::Limit, 10, ['limitPriceCents' => 9500]);
        expect($order->status)->toBe(OrderStatus::Pending);

        price(9800);
        expect($order->refresh()->status)->toBe(OrderStatus::Pending);

        price(9400);
        expect($order->refresh()->status)->toBe(OrderStatus::Filled)
            ->and($order->fill_price_cents)->toBe(9400);
    });

    it('fills a marketable limit order immediately', function () {
        $order = placeOrder(OrderSide::Buy, OrderType::Limit, 1, ['limitPriceCents' => 10100]);

        expect($order->status)->toBe(OrderStatus::Filled);
    });

    it('triggers a sell stop when the price falls through the stop', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $stop = placeOrder(OrderSide::Sell, OrderType::Stop, 10, ['stopPriceCents' => 9500]);

        price(9600);
        expect($stop->refresh()->status)->toBe(OrderStatus::Pending);

        price(9450);
        expect($stop->refresh()->status)->toBe(OrderStatus::Filled)
            ->and(position())->toBeNull();
    });

    it('only fills a stop-limit once triggered and the limit is respected', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $order = placeOrder(OrderSide::Sell, OrderType::StopLimit, 10, ['stopPriceCents' => 9500, 'limitPriceCents' => 9400]);

        price(9300); // stop geraakt, maar onder de limiet: niet gevuld
        expect($order->refresh()->stop_triggered_at)->not->toBeNull()
            ->and($order->status)->toBe(OrderStatus::Pending);

        price(9450);
        expect($order->refresh()->status)->toBe(OrderStatus::Filled)
            ->and($order->fill_price_cents)->toBe(9450);
    });

    it('can cancel an open order', function () {
        $order = placeOrder(OrderSide::Buy, OrderType::Limit, 1, ['limitPriceCents' => 5000]);

        $this->orders->cancel($order);
        price(4000);

        expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    });
});

describe('market hours', function () {
    it('keeps orders pending while the market is closed and fills them on open', function () {
        config(['trading.ignore_market_hours' => false]);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00', 'America/New_York')); // zaterdag

        $order = placeOrder(OrderSide::Buy, OrderType::Market, 1);
        expect($order->status)->toBe(OrderStatus::Pending);

        $this->orders->processOpenOrders();
        expect($order->refresh()->status)->toBe(OrderStatus::Pending);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:31', 'America/New_York'));
        $this->orders->processOpenOrders();
        expect($order->refresh()->status)->toBe(OrderStatus::Filled);
    });
});

describe('short selling', function () {
    it('opens a short, reserves margin and books profit when covered lower', function () {
        $order = placeOrder(OrderSide::Sell, OrderType::Market, 10, ['isShort' => true]);

        expect($order->status)->toBe(OrderStatus::Filled)
            ->and(position()->quantity)->toBe(-10)
            ->and($this->user->refresh()->cash_cents)->toBe(1_100_000)
            ->and($this->portfolio->summary($this->user)->buyingPowerCents)->toBe(1_100_000 - 150_000);

        $this->market->setPrice('AAPL', 9000);
        expect($this->portfolio->summary($this->user)->unrealizedPnlCents)->toBe(10_000);

        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $cover = $this->user->transactions()->latest('id')->first();

        expect($cover->realized_pnl_cents)->toBe(10_000)
            ->and(position())->toBeNull()
            ->and($this->user->refresh()->cash_cents)->toBe(1_010_000);
    });

    it('books a loss when the price rises', function () {
        placeOrder(OrderSide::Sell, OrderType::Market, 10, ['isShort' => true]);
        $this->market->setPrice('AAPL', 11000);

        placeOrder(OrderSide::Buy, OrderType::Market, 4);

        expect($this->user->transactions()->latest('id')->first()->realized_pnl_cents)->toBe(-4_000)
            ->and(position()->quantity)->toBe(-6)
            ->and(position()->cost_basis_cents)->toBe(60_000);
    });

    it('rejects a short that exceeds the margin requirement', function () {
        // $10.000 eigen geld, 150% onderpand: max ~$20.000 aan shorts = 200 aandelen.
        placeOrder(OrderSide::Sell, OrderType::Market, 201, ['isShort' => true]);
    })->throws(InvalidOrderException::class, 'koopkracht');

    it('allows shorting up to the margin limit', function () {
        $order = placeOrder(OrderSide::Sell, OrderType::Market, 200, ['isShort' => true]);

        expect($order->status)->toBe(OrderStatus::Filled);
    });

    it('rejects buying more than the short position', function () {
        placeOrder(OrderSide::Sell, OrderType::Market, 5, ['isShort' => true]);
        placeOrder(OrderSide::Buy, OrderType::Market, 6);
    })->throws(InvalidOrderException::class, 'short-positie');

    it('always allows covering, even when the account is under water', function () {
        placeOrder(OrderSide::Sell, OrderType::Market, 150, ['isShort' => true]);
        $this->market->setPrice('AAPL', 25000);

        $cover = placeOrder(OrderSide::Buy, OrderType::Market, 150);

        expect($cover->status)->toBe(OrderStatus::Filled)
            ->and(position())->toBeNull();
    });
});

describe('bracket orders', function () {
    it('keeps exit legs held until the entry fills', function () {
        $entry = placeOrder(OrderSide::Buy, OrderType::Limit, 10, [
            'limitPriceCents' => 9500, 'takeProfitCents' => 11000, 'stopLossCents' => 9000,
        ]);

        expect($entry->children)->toHaveCount(2)
            ->and($entry->children->pluck('status')->unique()->all())->toBe([OrderStatus::Held]);

        price(9400);

        expect($entry->children()->pluck('status')->unique()->all())->toBe([OrderStatus::Pending]);
    });

    it('cancels the stop-loss when take-profit fills (OCO)', function () {
        $entry = placeOrder(OrderSide::Buy, OrderType::Market, 10, ['takeProfitCents' => 11000, 'stopLossCents' => 9000]);

        price(11050);

        $statuses = $entry->children()->pluck('status', 'bracket_role');
        expect($statuses['take_profit'])->toBe(OrderStatus::Filled)
            ->and($statuses['stop_loss'])->toBe(OrderStatus::Cancelled)
            ->and(position())->toBeNull()
            ->and($this->user->refresh()->cash_cents)->toBe(1_010_500);
    });

    it('cancels the take-profit when stop-loss fills', function () {
        $entry = placeOrder(OrderSide::Buy, OrderType::Market, 10, ['takeProfitCents' => 11000, 'stopLossCents' => 9000]);

        price(8900);

        $statuses = $entry->children()->pluck('status', 'bracket_role');
        expect($statuses['stop_loss'])->toBe(OrderStatus::Filled)
            ->and($statuses['take_profit'])->toBe(OrderStatus::Cancelled);
    });

    it('cancels the children when the entry is cancelled', function () {
        $entry = placeOrder(OrderSide::Buy, OrderType::Limit, 10, [
            'limitPriceCents' => 9000, 'takeProfitCents' => 11000, 'stopLossCents' => 8000,
        ]);

        $this->orders->cancel($entry);

        expect($entry->children()->pluck('status')->unique()->all())->toBe([OrderStatus::Cancelled]);
    });

    it('works for short entries with cover legs', function () {
        $entry = placeOrder(OrderSide::Sell, OrderType::Market, 10, [
            'isShort' => true, 'takeProfitCents' => 9000, 'stopLossCents' => 11000,
        ]);

        price(8900);

        expect($entry->children()->where('bracket_role', 'take_profit')->first()->status)->toBe(OrderStatus::Filled)
            ->and(position())->toBeNull()
            ->and($this->user->refresh()->cash_cents)->toBe(1_011_000);
    });

    it('validates the direction of take-profit and stop-loss', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 1, ['takeProfitCents' => 9000]);
    })->throws(InvalidOrderException::class, 'Take-profit');
});

describe('trailing stop', function () {
    it('follows the price up and triggers after a pullback of the trail amount', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $trail = placeOrder(OrderSide::Sell, OrderType::TrailingStop, 10, ['trailAmountCents' => 500]);

        price(10800);
        price(11200);
        expect($trail->refresh()->trail_extreme_cents)->toBe(11200)
            ->and($trail->status)->toBe(OrderStatus::Pending);

        price(10800); // 4 onder top: nog niet
        expect($trail->refresh()->status)->toBe(OrderStatus::Pending);

        price(10700); // 5 onder top: trigger
        expect($trail->refresh()->status)->toBe(OrderStatus::Filled)
            ->and($trail->fill_price_cents)->toBe(10700)
            ->and(position())->toBeNull();
    });

    it('never moves the stop down', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $trail = placeOrder(OrderSide::Sell, OrderType::TrailingStop, 10, ['trailAmountCents' => 500]);

        price(10300);
        price(10100);

        expect($trail->refresh()->trail_extreme_cents)->toBe(10300);
    });

    it('supports a percentage trail', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $trail = placeOrder(OrderSide::Sell, OrderType::TrailingStop, 10, ['trailPercentBps' => 500]); // 5%

        price(12000);
        price(11500); // 4,17% onder top
        expect($trail->refresh()->status)->toBe(OrderStatus::Pending);

        price(11400); // 5% onder top
        expect($trail->refresh()->status)->toBe(OrderStatus::Filled);
    });

    it('trails a short position downwards with a buy trailing stop', function () {
        placeOrder(OrderSide::Sell, OrderType::Market, 10, ['isShort' => true]);
        $trail = placeOrder(OrderSide::Buy, OrderType::TrailingStop, 10, ['trailAmountCents' => 500]);

        price(9000);
        expect($trail->refresh()->trail_extreme_cents)->toBe(9000);

        price(9500);
        expect($trail->refresh()->status)->toBe(OrderStatus::Filled)
            ->and(position())->toBeNull();
    });

    it('requires exactly one of amount or percentage', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        placeOrder(OrderSide::Sell, OrderType::TrailingStop, 10, ['trailAmountCents' => 500, 'trailPercentBps' => 500]);
    })->throws(InvalidOrderException::class);
});

describe('engine and account', function () {
    it('rejects a pending order at fill time when the buying power is gone', function () {
        $limit = placeOrder(OrderSide::Buy, OrderType::Limit, 90, ['limitPriceCents' => 9000]);
        placeOrder(OrderSide::Buy, OrderType::Market, 50); // verbruikt $5.000

        price(8900);

        expect($limit->refresh()->status)->toBe(OrderStatus::Rejected)
            ->and($limit->reject_reason)->toContain('koopkracht');
    });

    it('runs through the artisan engine command', function () {
        $order = placeOrder(OrderSide::Buy, OrderType::Limit, 1, ['limitPriceCents' => 9000]);
        $this->market->setPrice('AAPL', 8900);

        $this->artisan('trading:engine', ['--once' => true])->assertSuccessful();

        expect($order->refresh()->status)->toBe(OrderStatus::Filled);
    });

    it('computes daily pnl against the previous close snapshot', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);
        $this->user->portfolioSnapshots()->create([
            'date' => now(config('trading.timezone'))->subDay()->toDateString(),
            'equity_cents' => 1_000_000, 'cash_cents' => 900_000,
        ]);
        $this->market->setPrice('AAPL', 10500);

        $summary = $this->portfolio->summary($this->user);

        expect($summary->dailyPnlCents)->toBe(5_000)
            ->and($summary->unrealizedPnlCents)->toBe(5_000);
    });

    it('resets the account to the (new) starting capital', function () {
        placeOrder(OrderSide::Buy, OrderType::Market, 10);

        $this->portfolio->resetAccount($this->user, 5_000_000);

        expect($this->user->refresh()->cash_cents)->toBe(5_000_000)
            ->and($this->user->starting_cash_cents)->toBe(5_000_000)
            ->and($this->user->positions()->count())->toBe(0)
            ->and($this->user->orders()->count())->toBe(0)
            ->and($this->user->transactions()->count())->toBe(0);
    });
});
