<?php

namespace App\Services\MarketData;

use App\Contracts\MarketDataProvider;
use App\Data\Candle;
use App\Data\MarketClock;
use App\Data\Quote;
use App\Enums\Timeframe;
use App\Exceptions\MarketDataUnavailableException;
use Illuminate\Support\Facades\Cache;

/**
 * Laag boven de provider: symboolnormalisatie, "laatst bekende koers"-fallback
 * en foutafhandeling, zodat de UI nooit crasht op API-problemen.
 */
class MarketDataService
{
    private ?string $error = null;

    public function __construct(private readonly MarketDataProvider $provider) {}

    public static function normalizeSymbol(string $symbol): string
    {
        return strtoupper(trim($symbol));
    }

    public static function isValidSymbol(string $symbol): bool
    {
        return preg_match('/^[A-Z]{1,5}(\.[A-Z])?$/', $symbol) === 1;
    }

    /**
     * Laatste foutmelding van de provider in dit verzoek, voor weergave in de UI.
     */
    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * @throws MarketDataUnavailableException wanneer er geen enkele koers (ook geen oude) bekend is.
     */
    public function quote(string $symbol): Quote
    {
        $symbol = self::normalizeSymbol($symbol);

        return $this->quotes([$symbol])[$symbol]
            ?? throw new MarketDataUnavailableException($this->error ?? "Geen koers beschikbaar voor {$symbol}.");
    }

    /**
     * @param  list<string>  $symbols
     * @return array<string, Quote> Symbolen zonder (oude) koers ontbreken.
     */
    public function quotes(array $symbols): array
    {
        $symbols = array_values(array_unique(array_map(self::normalizeSymbol(...), $symbols)));
        if ($symbols === []) {
            return [];
        }

        try {
            $quotes = $this->provider->quotes($symbols);
            foreach ($quotes as $symbol => $quote) {
                Cache::put("market:last_quote:{$symbol}", $quote->toArray(), now()->addDay());
            }
        } catch (MarketDataUnavailableException $e) {
            $this->error = $e->getMessage();
            $quotes = [];
        }

        foreach ($symbols as $symbol) {
            if (! isset($quotes[$symbol]) && ($last = Cache::get("market:last_quote:{$symbol}")) !== null && $this->error !== null) {
                $quotes[$symbol] = Quote::fromArray($last, isStale: true);
            }
        }

        return $quotes;
    }

    /**
     * @return list<Candle>
     */
    public function candles(string $symbol, Timeframe $timeframe, int $limit = 100): array
    {
        try {
            return $this->provider->candles(self::normalizeSymbol($symbol), $timeframe, $limit);
        } catch (MarketDataUnavailableException $e) {
            $this->error = $e->getMessage();

            return [];
        }
    }

    public function isMarketOpen(): bool
    {
        return $this->provider->isMarketOpen();
    }

    public function clock(): MarketClock
    {
        return $this->provider->marketClock();
    }

    /**
     * @return array<string, string> symbool => naam
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return array_filter(
            config('trading.symbols'),
            fn (string $name, string $symbol) => stripos($symbol, $query) !== false || stripos($name, $query) !== false,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
