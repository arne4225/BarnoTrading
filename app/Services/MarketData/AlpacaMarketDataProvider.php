<?php

namespace App\Services\MarketData;

use App\Contracts\MarketDataProvider;
use App\Data\Candle;
use App\Data\MarketClock;
use App\Data\Quote;
use App\Enums\Timeframe;
use App\Exceptions\MarketDataUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Alpaca Market Data (alleen lezen). Er wordt nooit iets naar de Alpaca
 * Trading API gestuurd: orders bestaan uitsluitend in onze eigen database.
 */
class AlpacaMarketDataProvider implements MarketDataProvider
{
    public function __construct(private readonly MarketCalendar $calendar) {}

    public function quote(string $symbol): Quote
    {
        return $this->quotes([$symbol])[$symbol]
            ?? throw new MarketDataUnavailableException("Geen koers beschikbaar voor {$symbol}.");
    }

    public function quotes(array $symbols): array
    {
        $ttl = config('trading.alpaca.quote_ttl_seconds');
        $quotes = [];
        $missing = [];

        foreach ($symbols as $symbol) {
            $cached = Cache::get("alpaca:quote:{$symbol}");
            if ($cached !== null) {
                $quotes[$symbol] = Quote::fromArray($cached);
            } else {
                $missing[] = $symbol;
            }
        }

        if ($missing !== []) {
            $response = $this->get('/v2/stocks/snapshots', [
                'symbols' => implode(',', $missing),
                'feed' => config('trading.alpaca.feed'),
            ]);

            foreach ($missing as $symbol) {
                $quote = $this->quoteFromSnapshot($symbol, $response[$symbol] ?? null);
                if ($quote !== null) {
                    Cache::put("alpaca:quote:{$symbol}", $quote->toArray(), $ttl);
                    $quotes[$symbol] = $quote;
                }
            }
        }

        return $quotes;
    }

    public function candles(string $symbol, Timeframe $timeframe, int $limit = 100): array
    {
        $key = "alpaca:candles:{$symbol}:{$timeframe->value}:{$limit}";

        $bars = Cache::remember($key, config('trading.alpaca.candle_ttl_seconds'), function () use ($symbol, $timeframe, $limit) {
            $tradingDays = (int) ceil($limit * $timeframe->seconds() / 23400);
            $lookbackDays = (int) ceil($tradingDays * 7 / 5) + 5;

            $response = $this->get("/v2/stocks/{$symbol}/bars", [
                'timeframe' => $timeframe->value,
                'start' => now()->subDays($lookbackDays)->toIso8601String(),
                'limit' => $limit,
                'sort' => 'desc',
                'adjustment' => 'raw',
                'feed' => config('trading.alpaca.feed'),
            ]);

            return $response['bars'] ?? [];
        });

        return array_map(fn (array $bar) => new Candle(
            CarbonImmutable::parse($bar['t']),
            $this->toCents($bar['o']),
            $this->toCents($bar['h']),
            $this->toCents($bar['l']),
            $this->toCents($bar['c']),
            (int) $bar['v'],
        ), array_reverse($bars));
    }

    public function marketClock(): MarketClock
    {
        return $this->calendar->clock();
    }

    public function isMarketOpen(): bool
    {
        return $this->calendar->isOpen();
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    private function quoteFromSnapshot(string $symbol, ?array $snapshot): ?Quote
    {
        $trade = $snapshot['latestTrade'] ?? null;
        if ($trade === null) {
            return null;
        }

        $price = $this->toCents($trade['p']);
        $latestQuote = $snapshot['latestQuote'] ?? [];
        $bid = ($latestQuote['bp'] ?? 0) > 0 ? $this->toCents($latestQuote['bp']) : $price;
        $ask = ($latestQuote['ap'] ?? 0) > 0 ? $this->toCents($latestQuote['ap']) : $price;

        return new Quote($symbol, $price, $bid, $ask, CarbonImmutable::parse($trade['t']));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $config = config('trading.alpaca');

        if (blank($config['key_id']) || blank($config['secret_key'])) {
            throw new MarketDataUnavailableException('Alpaca-sleutels ontbreken. Vul ALPACA_KEY_ID en ALPACA_SECRET_KEY in of gebruik MARKET_DATA_DRIVER=fake.');
        }

        try {
            $response = $this->client($config)->get($config['data_url'].$path, $query);
        } catch (ConnectionException) {
            throw new MarketDataUnavailableException('Alpaca is niet bereikbaar. Laatst bekende koersen worden getoond.');
        }

        $this->guard($response);

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config): PendingRequest
    {
        return Http::withHeaders([
            'APCA-API-KEY-ID' => $config['key_id'],
            'APCA-API-SECRET-KEY' => $config['secret_key'],
        ])->acceptJson()->timeout(8);
    }

    private function guard(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new MarketDataUnavailableException(match ($response->status()) {
            429 => 'Alpaca rate limit bereikt (gratis: 200 calls/min). Even geduld, koersen worden tijdelijk uit de cache getoond.',
            401, 403 => 'Alpaca weigert de sleutels of je abonnement geeft hier geen toegang toe.',
            400, 422 => 'Alpaca kon dit symbool of deze aanvraag niet verwerken.',
            default => "Alpaca gaf een fout terug (HTTP {$response->status()}).",
        });
    }

    private function toCents(int|float|string $dollars): int
    {
        return (int) round(((float) $dollars) * 100);
    }
}
