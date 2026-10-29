<?php

namespace App\Services;

use App\Data\PortfolioSummary;
use App\Data\PositionView;
use App\Enums\OrderSide;
use App\Exceptions\InvalidOrderException;
use App\Models\Order;
use App\Models\PortfolioSnapshot;
use App\Models\Position;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\Facades\DB;

/**
 * Cash, posities, P&L en boekhouding van uitgevoerde orders.
 *
 * Boekhouding: de kostenbasis van een positie is altijd positief. Bij een long
 * is dat wat er (incl. commissie) is betaald, bij een short wat er (excl.
 * commissie) is ontvangen. Daardoor geldt altijd: equity = cash + som(qty * koers)
 * en realized + unrealized P&L = equity - startkapitaal.
 */
class PortfolioService
{
    public function __construct(private readonly MarketDataService $marketData) {}

    public function positionFor(User $user, string $symbol): ?Position
    {
        return $user->positions()->where('symbol', $symbol)->first();
    }

    /**
     * Voorkomt dat een order door nul heen gaat of aandelen verkoopt die er niet zijn.
     *
     * @throws InvalidOrderException
     */
    public function assertTradeAllowed(int $currentQuantity, OrderSide $side, bool $isShort, int $quantity): void
    {
        if ($side === OrderSide::Buy) {
            if ($currentQuantity < 0 && $quantity > abs($currentQuantity)) {
                throw new InvalidOrderException('Je kunt maximaal je short-positie van '.abs($currentQuantity).' aandelen terugkopen. Dek eerst af, koop daarna long.');
            }

            return;
        }

        if ($isShort) {
            if ($currentQuantity > 0) {
                throw new InvalidOrderException('Je hebt een long-positie in dit aandeel. Verkoop die eerst voordat je short gaat.');
            }

            return;
        }

        if ($currentQuantity <= 0) {
            throw new InvalidOrderException('Je bezit geen aandelen om te verkopen. Kies "short verkopen" als je bewust short wilt gaan.');
        }
        if ($quantity > $currentQuantity) {
            throw new InvalidOrderException("Je bezit maar {$currentQuantity} aandelen. Verkopen van meer vereist een bewuste short, na het sluiten van je long.");
        }
    }

    /**
     * Marktwaarde van alle shortposities (positief getal), optioneel met een
     * vaste koers voor één symbool (de koers van de order die wordt getoetst).
     */
    public function shortValueCents(User $user, ?string $symbol = null, ?int $priceCents = null): int
    {
        $shorts = $user->positions()->where('quantity', '<', 0)->get();
        $quotes = $this->marketData->quotes($shorts->pluck('symbol')->all());

        return $shorts->sum(function (Position $position) use ($quotes, $symbol, $priceCents): int {
            $price = $position->symbol === $symbol && $priceCents !== null
                ? $priceCents
                : ($quotes[$position->symbol]->priceCents ?? $position->averagePriceCents());

            return abs($position->quantity) * $price;
        });
    }

    public function reservedMarginCents(int $shortValueCents): int
    {
        return intdiv($shortValueCents * config('trading.short_margin_percent') + 50, 100);
    }

    /**
     * Cash die vrij is voor nieuwe aankopen: cash min de onderpandseis voor shorts.
     */
    public function buyingPowerCents(User $user): int
    {
        return $user->cash_cents - $this->reservedMarginCents($this->shortValueCents($user));
    }

    /**
     * Boekt een uitvoering: cash, positie en transactie. Roep aan binnen een
     * databasetransactie met geldige order (zie OrderService).
     *
     * @throws InvalidOrderException
     */
    public function applyFill(Order $order, int $priceCents, int $commissionCents): Transaction
    {
        $user = User::query()->lockForUpdate()->findOrFail($order->user_id);
        $position = $user->positions()->where('symbol', $order->symbol)->lockForUpdate()->first();
        $current = $position?->quantity ?? 0;
        $quantity = $order->quantity;
        $gross = $quantity * $priceCents;
        $realized = 0;

        $this->assertTradeAllowed($current, $order->side, $order->is_short, $quantity);

        $isOpeningLeg = ($order->side === OrderSide::Buy && $current >= 0) || ($order->side === OrderSide::Sell && $order->is_short);

        $position ??= new Position(['user_id' => $user->id, 'symbol' => $order->symbol, 'quantity' => 0, 'cost_basis_cents' => 0]);

        if ($order->side === OrderSide::Buy && $current < 0) {
            $released = $this->releasedBasis($position, $quantity);
            $realized = $released - ($gross + $commissionCents);
            $user->cash_cents -= $gross + $commissionCents;
            $position->quantity += $quantity;
            $position->cost_basis_cents -= $released;
        } elseif ($order->side === OrderSide::Buy) {
            $user->cash_cents -= $gross + $commissionCents;
            $position->quantity += $quantity;
            $position->cost_basis_cents += $gross + $commissionCents;
        } elseif (! $order->is_short) {
            $released = $this->releasedBasis($position, $quantity);
            $realized = ($gross - $commissionCents) - $released;
            $user->cash_cents += $gross - $commissionCents;
            $position->quantity -= $quantity;
            $position->cost_basis_cents -= $released;
        } else {
            $user->cash_cents += $gross - $commissionCents;
            $position->quantity -= $quantity;
            $position->cost_basis_cents += max(0, $gross - $commissionCents);
        }

        $user->save();
        if ($position->quantity === 0) {
            $position->exists && $position->delete();
        } else {
            $position->save();
        }

        $trade = $this->updateTrade($user, $order, $current, $position->quantity, $isOpeningLeg, $priceCents, $realized, $commissionCents);

        return Transaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'trade_id' => $trade->id,
            'symbol' => $order->symbol,
            'side' => $order->side,
            'is_short' => $order->is_short,
            'quantity' => $quantity,
            'price_cents' => $priceCents,
            'commission_cents' => $commissionCents,
            'realized_pnl_cents' => $realized,
            'executed_at' => now(),
        ]);
    }

    /**
     * Houdt de round-trip (Trade) bij: opent bij een positie van 0, sluit bij terugkeer naar 0.
     */
    private function updateTrade(User $user, Order $order, int $before, int $after, bool $isOpeningLeg, int $priceCents, int $realized, int $commissionCents): Trade
    {
        $trade = $before === 0
            ? null
            : $user->trades()->where('symbol', $order->symbol)->whereNull('closed_at')->latest('id')->first();

        $trade ??= $user->trades()->create([
            'symbol' => $order->symbol,
            'direction' => $order->side === OrderSide::Buy ? 'long' : 'short',
            'opened_at' => now(),
        ]);

        $gross = $order->quantity * $priceCents;
        $trade->peak_quantity = max($trade->peak_quantity, abs($after));
        $trade->commission_cents += $commissionCents;
        $trade->realized_pnl_cents += $realized;

        if ($isOpeningLeg) {
            $trade->open_quantity += $order->quantity;
            $trade->open_value_cents += $gross;
        } else {
            $trade->close_quantity += $order->quantity;
            $trade->close_value_cents += $gross;
        }

        if ($after === 0) {
            $trade->closed_at = now();
        }

        $trade->save();

        return $trade;
    }

    public function summary(User $user): PortfolioSummary
    {
        $user->refresh();
        $positions = $user->positions()->orderBy('symbol')->get();
        $quotes = $this->marketData->quotes($positions->pluck('symbol')->all());

        $views = [];
        $marketValue = 0;
        $unrealized = 0;
        $shortValue = 0;

        foreach ($positions as $position) {
            $quote = $quotes[$position->symbol] ?? null;
            $price = $quote->priceCents ?? $position->averagePriceCents();
            $value = $position->quantity * $price;
            $pnl = $position->quantity > 0
                ? $value - $position->cost_basis_cents
                : $position->cost_basis_cents + $value;

            $marketValue += $value;
            $unrealized += $pnl;
            $shortValue += $position->quantity < 0 ? -$value : 0;
            $views[] = new PositionView(
                $position,
                $price,
                $value,
                $pnl,
                $position->cost_basis_cents > 0 ? round($pnl / $position->cost_basis_cents * 100, 2) : 0.0,
                $quote,
            );
        }

        $equity = $user->cash_cents + $marketValue;
        $summary = new PortfolioSummary(
            $user->cash_cents,
            $marketValue,
            $equity,
            $unrealized,
            $equity - $this->dailyBaselineCents($user),
            $equity - $user->starting_cash_cents,
            $user->cash_cents - $this->reservedMarginCents($shortValue),
            $views,
        );

        $this->recordSnapshot($user, $equity);

        return $summary;
    }

    /**
     * Equity bij de laatste sluiting voor vandaag (Eastern Time), of anders het startkapitaal.
     */
    public function dailyBaselineCents(User $user): int
    {
        $today = now(config('trading.timezone'))->toDateString();

        return $user->portfolioSnapshots()->where('date', '<', $today)->latest('date')->value('equity_cents')
            ?? $user->starting_cash_cents;
    }

    public function recordSnapshot(User $user, int $equityCents): void
    {
        PortfolioSnapshot::updateOrCreate(
            ['user_id' => $user->id, 'date' => now(config('trading.timezone'))->toDateString()],
            ['equity_cents' => $equityCents, 'cash_cents' => $user->cash_cents],
        );
    }

    public function snapshotAll(): int
    {
        $count = 0;
        User::query()->each(function (User $user) use (&$count) {
            $this->summary($user);
            $count++;
        });

        return $count;
    }

    /**
     * Wist orders, posities, transacties en snapshots en zet cash terug.
     */
    public function resetAccount(User $user, ?int $startingCashCents = null): void
    {
        DB::transaction(function () use ($user, $startingCashCents) {
            $user->orders()->delete();
            $user->positions()->delete();
            $user->transactions()->delete();
            $user->trades()->delete();
            $user->portfolioSnapshots()->delete();

            $starting = $startingCashCents ?? $user->starting_cash_cents;
            $user->forceFill(['starting_cash_cents' => $starting, 'cash_cents' => $starting])->save();
        });
    }

    private function releasedBasis(Position $position, int $closingQuantity): int
    {
        $open = abs($position->quantity);

        return $closingQuantity === $open
            ? $position->cost_basis_cents
            : (int) round($position->cost_basis_cents * $closingQuantity / $open);
    }
}
