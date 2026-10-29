<?php

namespace App\Services;

use App\Data\PlaceOrderData;
use App\Data\Quote;
use App\Enums\BracketRole;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\InvalidOrderException;
use App\Exceptions\MarketDataUnavailableException;
use App\Models\Order;
use App\Models\User;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\Facades\DB;

/**
 * Plaatsen, annuleren en uitvoeren van gesimuleerde orders. Er wordt nooit een
 * order naar een broker gestuurd.
 */
class OrderService
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly PortfolioService $portfolio,
        private readonly RiskService $risk,
    ) {}

    /**
     * @throws InvalidOrderException
     */
    public function place(User $user, PlaceOrderData $data): Order
    {
        $symbol = MarketDataService::normalizeSymbol($data->symbol);
        $this->validateShape($data, $symbol);
        $this->risk->assertWithinDailyLossLimit($user, $data);

        try {
            $quote = $this->marketData->quote($symbol);
        } catch (MarketDataUnavailableException $e) {
            throw new InvalidOrderException("Kan {$symbol} niet controleren: ".$e->getMessage());
        }

        $position = $this->portfolio->positionFor($user, $symbol);
        $this->portfolio->assertTradeAllowed($position?->quantity ?? 0, $data->side, $data->isShort, $data->quantity);
        $this->validateBracket($data, $this->referencePrice($data, $quote, $user));

        $this->assertBuyingPower($user, $symbol, $data->side, $data->quantity, $this->referencePrice($data, $quote, $user), $user->effectiveCommissionCents());

        $order = DB::transaction(function () use ($user, $data, $symbol, $quote) {
            $order = $user->orders()->create([
                'symbol' => $symbol,
                'side' => $data->side,
                'type' => $data->type,
                'status' => OrderStatus::Pending,
                'is_short' => $data->isShort,
                'quantity' => $data->quantity,
                'limit_price_cents' => $data->limitPriceCents,
                'stop_price_cents' => $data->stopPriceCents,
                'trail_amount_cents' => $data->trailAmountCents,
                'trail_percent_bps' => $data->trailPercentBps,
                'trail_extreme_cents' => $data->type === OrderType::TrailingStop ? $quote->priceCents : null,
            ]);

            $this->createBracketChildren($order, $data);

            return $order;
        });

        if ($this->marketData->isMarketOpen()) {
            $this->evaluate($order, $quote);
        }

        return $order->refresh();
    }

    public function cancel(Order $order): void
    {
        if (! $order->isActive()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Cancelled]);
            $order->children()->whereIn('status', [OrderStatus::Held, OrderStatus::Pending])
                ->update(['status' => OrderStatus::Cancelled]);
        });
    }

    /**
     * Eén engine-ronde: controleert alle open orders tegen de actuele koersen.
     *
     * @return int aantal uitgevoerde orders
     */
    public function processOpenOrders(): int
    {
        if (! $this->marketData->isMarketOpen()) {
            return 0;
        }

        $orders = Order::query()->where('status', OrderStatus::Pending)->orderBy('id')->get();
        if ($orders->isEmpty()) {
            return 0;
        }

        $quotes = $this->marketData->quotes($orders->pluck('symbol')->unique()->all());
        $filled = 0;

        foreach ($orders as $order) {
            $quote = $quotes[$order->symbol] ?? null;
            if ($quote === null || $quote->isStale) {
                continue;
            }
            if ($this->evaluate($order->fresh(), $quote)) {
                $filled++;
            }
        }

        return $filled;
    }

    /**
     * Toetst één order aan een koers en voert hem uit als de voorwaarden gehaald zijn.
     */
    public function evaluate(Order $order, Quote $quote): bool
    {
        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        $price = $quote->priceCents;
        $isBuy = $order->side === OrderSide::Buy;
        $user = $order->user;

        $fillPrice = match ($order->type) {
            OrderType::Market => $this->withSlippage($price, $isBuy, $user),
            OrderType::Limit => $this->limitFill($order, $price),
            OrderType::Stop => $this->stopTriggered($order, $price) ? $this->withSlippage($price, $isBuy, $user) : null,
            OrderType::StopLimit => $this->evaluateStopLimit($order, $price),
            OrderType::TrailingStop => $this->evaluateTrailingStop($order, $price, $user),
        };

        if ($fillPrice === null) {
            return false;
        }

        return $this->fill($order, $fillPrice);
    }

    private function limitFill(Order $order, int $price): ?int
    {
        $limit = $order->limit_price_cents;

        return match ($order->side) {
            OrderSide::Buy => $price <= $limit ? $price : null,
            OrderSide::Sell => $price >= $limit ? $price : null,
        };
    }

    private function stopTriggered(Order $order, int $price): bool
    {
        return $order->side === OrderSide::Buy
            ? $price >= $order->stop_price_cents
            : $price <= $order->stop_price_cents;
    }

    private function evaluateStopLimit(Order $order, int $price): ?int
    {
        if ($order->stop_triggered_at === null) {
            if (! $this->stopTriggered($order, $price)) {
                return null;
            }
            $order->update(['stop_triggered_at' => now()]);
        }

        return $this->limitFill($order, $price);
    }

    private function evaluateTrailingStop(Order $order, int $price, User $user): ?int
    {
        $isBuy = $order->side === OrderSide::Buy;
        $extreme = $order->trail_extreme_cents ?? $price;
        $extreme = $isBuy ? min($extreme, $price) : max($extreme, $price);

        if ($extreme !== $order->trail_extreme_cents) {
            $order->update(['trail_extreme_cents' => $extreme]);
        }

        $distance = $order->trail_amount_cents
            ?? (int) round($extreme * $order->trail_percent_bps / 10000);

        $triggered = $isBuy ? $price >= $extreme + $distance : $price <= $extreme - $distance;

        return $triggered ? $this->withSlippage($price, $isBuy, $user) : null;
    }

    private function fill(Order $order, int $fillPrice): bool
    {
        $commission = $order->user->effectiveCommissionCents();

        return DB::transaction(function () use ($order, $fillPrice, $commission) {
            try {
                $this->assertBuyingPower($order->user, $order->symbol, $order->side, $order->quantity, $fillPrice, $commission);
                $this->portfolio->applyFill($order, $fillPrice, $commission);
            } catch (InvalidOrderException $e) {
                $this->reject($order, $e->getMessage());

                return false;
            }

            $order->update([
                'status' => OrderStatus::Filled,
                'fill_price_cents' => $fillPrice,
                'commission_cents' => $commission,
                'filled_at' => now(),
            ]);

            $this->afterFill($order);

            return true;
        });
    }

    private function afterFill(Order $order): void
    {
        if ($order->bracket_role !== null) {
            // OCO: de uitgevoerde poot annuleert de andere poot.
            Order::query()
                ->where('parent_order_id', $order->parent_order_id)
                ->whereKeyNot($order->id)
                ->whereIn('status', [OrderStatus::Held, OrderStatus::Pending])
                ->update(['status' => OrderStatus::Cancelled]);

            return;
        }

        $order->children()->where('status', OrderStatus::Held)->update(['status' => OrderStatus::Pending]);
    }

    private function reject(Order $order, string $reason): void
    {
        $order->update(['status' => OrderStatus::Rejected, 'reject_reason' => $reason]);
        $order->children()->whereIn('status', [OrderStatus::Held, OrderStatus::Pending])
            ->update(['status' => OrderStatus::Cancelled]);
    }

    /**
     * Eén regel voor kopen én shorten: na de uitvoering mag de vrije cash (cash
     * min onderpand voor shorts) niet negatief zijn, tenzij de order de positie
     * verkleint en de vrije cash daardoor niet slechter wordt.
     *
     * @throws InvalidOrderException
     */
    private function assertBuyingPower(User $user, string $symbol, OrderSide $side, int $quantity, int $priceCents, int $commissionCents): void
    {
        $user->refresh();
        $gross = $quantity * $priceCents;
        $shortBefore = $this->portfolio->shortValueCents($user, $symbol, $priceCents);
        $currentQuantity = $this->portfolio->positionFor($user, $symbol)?->quantity ?? 0;

        $signed = $side->sign() * $quantity;
        $shortAfterQuantity = max(0, -($currentQuantity + $signed));
        $shortBeforeQuantity = max(0, -$currentQuantity);
        $shortAfter = $shortBefore + ($shortAfterQuantity - $shortBeforeQuantity) * $priceCents;

        $cashAfter = $user->cash_cents + ($side === OrderSide::Sell ? $gross - $commissionCents : -$gross - $commissionCents);

        $availableBefore = $user->cash_cents - $this->portfolio->reservedMarginCents($shortBefore);
        $availableAfter = $cashAfter - $this->portfolio->reservedMarginCents($shortAfter);

        if ($availableAfter < 0 && $availableAfter < $availableBefore) {
            $needed = $availableBefore - $availableAfter;
            throw new InvalidOrderException(sprintf(
                'Onvoldoende koopkracht: deze order vraagt $%s, beschikbaar is $%s.',
                number_format($needed / 100, 2),
                number_format(max(0, $availableBefore) / 100, 2),
            ));
        }
    }

    /**
     * @throws InvalidOrderException
     */
    private function validateShape(PlaceOrderData $data, string $symbol): void
    {
        if (! MarketDataService::isValidSymbol($symbol)) {
            throw new InvalidOrderException("'{$symbol}' is geen geldig US-symbool.");
        }
        if ($data->quantity < 1) {
            throw new InvalidOrderException('Het aantal moet minimaal 1 zijn.');
        }
        if ($data->isShort && $data->side !== OrderSide::Sell) {
            throw new InvalidOrderException('Alleen verkopen kan een short zijn.');
        }
        if ($data->type->needsLimitPrice() && ($data->limitPriceCents ?? 0) < 1) {
            throw new InvalidOrderException('Een limietprijs is verplicht voor dit ordertype.');
        }
        if ($data->type->needsStopPrice() && ($data->stopPriceCents ?? 0) < 1) {
            throw new InvalidOrderException('Een stopprijs is verplicht voor dit ordertype.');
        }
        if ($data->type === OrderType::TrailingStop) {
            $hasAmount = ($data->trailAmountCents ?? 0) > 0;
            $hasPercent = ($data->trailPercentBps ?? 0) > 0;
            if ($hasAmount === $hasPercent) {
                throw new InvalidOrderException('Kies bij een trailing stop óf een bedrag óf een percentage.');
            }
            if ($data->takeProfitCents !== null || $data->stopLossCents !== null) {
                throw new InvalidOrderException('Een trailing stop kan geen bracket (take-profit/stop-loss) hebben.');
            }
        }
    }

    /**
     * @throws InvalidOrderException
     */
    private function validateBracket(PlaceOrderData $data, int $reference): void
    {
        $isBuy = $data->side === OrderSide::Buy;

        if ($data->takeProfitCents !== null && ($isBuy ? $data->takeProfitCents <= $reference : $data->takeProfitCents >= $reference)) {
            throw new InvalidOrderException('Take-profit moet '.($isBuy ? 'boven' : 'onder').' de instapprijs liggen.');
        }
        if ($data->stopLossCents !== null && ($isBuy ? $data->stopLossCents >= $reference : $data->stopLossCents <= $reference)) {
            throw new InvalidOrderException('Stop-loss moet '.($isBuy ? 'onder' : 'boven').' de instapprijs liggen.');
        }
    }

    private function createBracketChildren(Order $parent, PlaceOrderData $data): void
    {
        $legs = [
            [BracketRole::TakeProfit, OrderType::Limit, $data->takeProfitCents],
            [BracketRole::StopLoss, OrderType::Stop, $data->stopLossCents],
        ];

        foreach ($legs as [$role, $type, $price]) {
            if ($price === null) {
                continue;
            }

            $parent->children()->create([
                'user_id' => $parent->user_id,
                'symbol' => $parent->symbol,
                'side' => $parent->side->opposite(),
                'type' => $type,
                'status' => OrderStatus::Held,
                'is_short' => false,
                'quantity' => $parent->quantity,
                'bracket_role' => $role,
                'limit_price_cents' => $type === OrderType::Limit ? $price : null,
                'stop_price_cents' => $type === OrderType::Stop ? $price : null,
            ]);
        }
    }

    private function referencePrice(PlaceOrderData $data, Quote $quote, User $user): int
    {
        return match ($data->type) {
            OrderType::Limit, OrderType::StopLimit => $data->limitPriceCents,
            OrderType::Stop => $data->stopPriceCents,
            default => $data->side === OrderSide::Buy ? $this->withSlippage($quote->priceCents, true, $user) : $quote->priceCents,
        };
    }

    private function withSlippage(int $priceCents, bool $isBuy, User $user): int
    {
        $bps = $user->effectiveSlippageBps();
        $adjustment = (int) round($priceCents * $bps / 10000);

        return $isBuy ? $priceCents + $adjustment : max(1, $priceCents - $adjustment);
    }
}
