<?php

namespace App\Services;

use App\Exceptions\InvalidAlertException;
use App\Exceptions\MarketDataUnavailableException;
use App\Models\PriceAlert;
use App\Models\User;
use App\Services\MarketData\MarketDataService;
use Illuminate\Database\Eloquent\Collection;

class AlertService
{
    public function __construct(private readonly MarketDataService $marketData) {}

    /**
     * Maakt een alert; de richting volgt uit de huidige koers (erboven = "above").
     *
     * @throws InvalidAlertException
     */
    public function create(User $user, string $symbol, int $priceCents): PriceAlert
    {
        $symbol = MarketDataService::normalizeSymbol($symbol);

        try {
            $current = $this->marketData->quote($symbol)->priceCents;
        } catch (MarketDataUnavailableException $e) {
            throw new InvalidAlertException("Kan de koers van {$symbol} niet ophalen: ".$e->getMessage());
        }

        if ($priceCents === $current) {
            throw new InvalidAlertException('Het alertniveau is gelijk aan de huidige koers.');
        }
        if ($user->priceAlerts()->whereNull('triggered_at')->count() >= 50) {
            throw new InvalidAlertException('Je hebt het maximum van 50 actieve alerts bereikt.');
        }

        return $user->priceAlerts()->create([
            'symbol' => $symbol,
            'direction' => $priceCents > $current ? 'above' : 'below',
            'price_cents' => $priceCents,
        ]);
    }

    /**
     * Controleert actieve alerts (van één gebruiker of van iedereen) tegen de koersen.
     *
     * @return int aantal nieuw afgegane alerts
     */
    public function check(?User $user = null): int
    {
        $alerts = PriceAlert::query()->whereNull('triggered_at')
            ->when($user, fn ($q) => $q->where('user_id', $user->id))->get();
        if ($alerts->isEmpty()) {
            return 0;
        }

        $quotes = $this->marketData->quotes($alerts->pluck('symbol')->unique()->all());
        $triggered = 0;

        foreach ($alerts as $alert) {
            $quote = $quotes[$alert->symbol] ?? null;
            if ($quote === null || $quote->isStale) {
                continue;
            }

            $hit = $alert->direction === 'above'
                ? $quote->priceCents >= $alert->price_cents
                : $quote->priceCents <= $alert->price_cents;

            if ($hit) {
                $alert->update(['triggered_at' => now(), 'triggered_price_cents' => $quote->priceCents]);
                $triggered++;
            }
        }

        return $triggered;
    }

    /**
     * @return Collection<int, PriceAlert>
     */
    public function unacknowledged(User $user): Collection
    {
        return $user->priceAlerts()->whereNotNull('triggered_at')->whereNull('acknowledged_at')->orderBy('triggered_at')->get();
    }
}
