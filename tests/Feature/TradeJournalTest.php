<?php

use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Models\Trade;
use App\Models\User;
use App\Services\MarketData\FakeMarketDataProvider;
use App\Services\OrderService;
use App\Services\PortfolioService;
use App\Services\StatisticsService;

beforeEach(function () {
    $this->market = app(FakeMarketDataProvider::class)->freeze();
    $this->market->setPrice('AAPL', 10000);
    $this->user = User::factory()->create(['starting_cash_cents' => 1_000_000, 'cash_cents' => 1_000_000]);
    $this->orders = app(OrderService::class);
});

function trade(OrderSide $side, int $qty, bool $short = false): void
{
    test()->orders->place(test()->user, new PlaceOrderData('AAPL', $side, OrderType::Market, $qty, isShort: $short));
}

describe('trade lifecycle', function () {
    it('groups fills into one round-trip trade with average entry and exit', function () {
        trade(OrderSide::Buy, 10);
        $this->market->setPrice('AAPL', 12000);
        trade(OrderSide::Buy, 10);
        $this->market->setPrice('AAPL', 13000);
        trade(OrderSide::Sell, 5);
        trade(OrderSide::Sell, 15);

        $trade = $this->user->trades()->sole();

        expect($trade->isClosed())->toBeTrue()
            ->and($trade->direction)->toBe('long')
            ->and($trade->peak_quantity)->toBe(20)
            ->and($trade->averageEntryCents())->toBe(11000)
            ->and($trade->averageExitCents())->toBe(13000)
            ->and($trade->realized_pnl_cents)->toBe(20 * 2000)
            ->and($this->user->transactions()->where('trade_id', $trade->id)->count())->toBe(4)
            ->and($this->user->transactions()->sum('realized_pnl_cents'))->toBe($trade->realized_pnl_cents);
    });

    it('keeps a trade open while the position is open and starts a new one after closing', function () {
        trade(OrderSide::Buy, 10);
        trade(OrderSide::Sell, 4);

        expect($this->user->trades()->sole()->isClosed())->toBeFalse();

        trade(OrderSide::Sell, 6);
        trade(OrderSide::Buy, 3);

        expect($this->user->trades()->count())->toBe(2)
            ->and($this->user->trades()->whereNull('closed_at')->count())->toBe(1);
    });

    it('tracks short trades and includes commissions in the result', function () {
        config(['trading.commission_cents' => 100]);
        trade(OrderSide::Sell, 10, short: true);
        $this->market->setPrice('AAPL', 9000);
        trade(OrderSide::Buy, 10);

        $trade = $this->user->trades()->sole();

        expect($trade->direction)->toBe('short')
            ->and($trade->commission_cents)->toBe(200)
            ->and($trade->realized_pnl_cents)->toBe(10_000 - 200);
    });

    it('is removed when the account is reset', function () {
        trade(OrderSide::Buy, 10);

        app(PortfolioService::class)->resetAccount($this->user);

        expect($this->user->trades()->count())->toBe(0);
    });
});

describe('journal', function () {
    beforeEach(fn () => $this->actingAs($this->user));

    it('stores notes, normalised tags and a self rating', function () {
        $trade = Trade::factory()->for($this->user)->create();

        $this->patch("/journal/{$trade->id}", [
            'entry_reason' => 'Breakout boven weerstand', 'note' => 'Te vroeg uitgestapt',
            'tags' => ' Breakout, FOMO ,breakout,, plan ', 'self_rating' => 4,
        ])->assertSessionHas('status');

        $trade->refresh();
        expect($trade->tags)->toBe(['breakout', 'fomo', 'plan'])
            ->and($trade->self_rating)->toBe(4)
            ->and($trade->note)->toBe('Te vroeg uitgestapt');
    });

    it('validates the rating and lets users edit only their own trades', function () {
        $mine = Trade::factory()->for($this->user)->create();
        $theirs = Trade::factory()->create();

        $this->patch("/journal/{$mine->id}", ['self_rating' => 9])->assertSessionHasErrors('self_rating');
        $this->patch("/journal/{$theirs->id}", ['note' => 'hack'])->assertForbidden();
        expect($theirs->refresh()->note)->toBeNull();
    });

    it('lists only the own trades', function () {
        Trade::factory()->for($this->user)->create(['symbol' => 'MSFT']);
        Trade::factory()->create(['symbol' => 'TSLA']);

        $this->get('/journal')->assertOk()->assertSee('MSFT')->assertDontSee('TSLA');
    });

    it('exports own trades as CSV with decimal amounts and neutralised formulas', function () {
        Trade::factory()->for($this->user)->create([
            'symbol' => 'MSFT', 'realized_pnl_cents' => -1234, 'tags' => ['a', 'b'], 'note' => '=HYPERLINK("http://evil")',
        ]);
        Trade::factory()->create(['symbol' => 'TSLA']);

        $response = $this->get('/journal/export')->assertOk();
        $csv = $response->streamedContent();

        expect($response->headers->get('content-type'))->toContain('text/csv')
            ->and($csv)->toContain('id,symbol,direction')
            ->and($csv)->toContain('MSFT')->not->toContain('TSLA')
            ->and($csv)->toContain('-12.34')->toContain('a|b')
            ->and($csv)->toContain("'=HYPERLINK");
    });
});

describe('statistics', function () {
    it('computes statistics for a user and period from closed trades only', function () {
        Trade::factory()->for($this->user)->create(['realized_pnl_cents' => 5000, 'tags' => ['plan']]);
        Trade::factory()->for($this->user)->create(['realized_pnl_cents' => -2000]);
        Trade::factory()->for($this->user)->create(['realized_pnl_cents' => 9999, 'closed_at' => now()->subDays(60)]);
        Trade::factory()->for($this->user)->open()->create();
        Trade::factory()->create(['realized_pnl_cents' => 777]); // andere gebruiker

        $all = app(StatisticsService::class)->forUser($this->user, 'all');
        $month = app(StatisticsService::class)->forUser($this->user, 'month');

        expect($all['summary']['count'])->toBe(3)
            ->and($month['summary']['count'])->toBe(2)
            ->and($month['summary']['total_pnl_cents'])->toBe(3000)
            ->and($month['by_tag']['plan']['count'])->toBe(1);
    });

    it('builds an equity curve starting at the starting capital with drawdown', function () {
        $this->user->portfolioSnapshots()->create(['date' => '2026-10-27', 'equity_cents' => 1_100_000, 'cash_cents' => 1]);
        $this->user->portfolioSnapshots()->create(['date' => '2026-10-28', 'equity_cents' => 990_000, 'cash_cents' => 1]);

        $curve = app(StatisticsService::class)->equityCurve($this->user);

        expect($curve)->toHaveCount(3)
            ->and($curve[0])->toMatchArray(['date' => '2026-10-26', 'equity_cents' => 1_000_000])
            ->and($curve[2]['drawdown_percent'])->toBe(-10.0);
    });

    it('serves the statistics page and caches the heavy calculation', function () {
        Trade::factory()->for($this->user)->create();
        $this->actingAs($this->user);

        $this->get('/statistics')->assertOk()->assertSee('Profit factor')->assertSee('Win rate');
        $this->get('/statistics?period=week')->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        app(StatisticsService::class)->forUser($this->user, 'all');
        $first = $queries;
        app(StatisticsService::class)->forUser($this->user, 'all');

        expect($queries - $first)->toBeLessThanOrEqual(2);
    });
});
