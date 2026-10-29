<?php

namespace App\Services\MarketData;

use App\Contracts\MarketDataProvider;
use App\Data\Candle;
use App\Data\MarketClock;
use App\Data\Quote;
use App\Enums\Timeframe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Gesimuleerde koersen (random walk). De staat zit in de cache, zodat webproces
 * en order engine dezelfde koersen zien. In tests kan met setPrice() en
 * freeze() volledig deterministisch gewerkt worden.
 */
class FakeMarketDataProvider implements MarketDataProvider
{
    private const VOLATILITY_PER_STEP = 0.0008;

    private bool $frozen = false;

    public function __construct(private readonly MarketCalendar $calendar) {}

    public function freeze(): static
    {
        $this->frozen = true;

        return $this;
    }

    public function setPrice(string $symbol, int $priceCents): static
    {
        Cache::put($this->key($symbol), ['price' => $priceCents, 'ts' => now()->getTimestamp()], now()->addDay());

        return $this;
    }

    public function quote(string $symbol): Quote
    {
        $state = Cache::get($this->key($symbol)) ?? ['price' => $this->basePrice($symbol), 'ts' => now()->getTimestamp()];

        if (! $this->frozen) {
            $steps = min(60, max(0, now()->getTimestamp() - $state['ts']));
            for ($i = 0; $i < $steps; $i++) {
                $change = (mt_rand(-1000, 1000) / 1000) * self::VOLATILITY_PER_STEP;
                $state['price'] = max(100, (int) round($state['price'] * (1 + $change)));
            }
            if ($steps > 0) {
                $state['ts'] = now()->getTimestamp();
            }
        }

        Cache::put($this->key($symbol), $state, now()->addDay());

        return new Quote(
            $symbol,
            $state['price'],
            max(1, $state['price'] - 1),
            $state['price'] + 1,
            CarbonImmutable::now(),
        );
    }

    public function quotes(array $symbols): array
    {
        $quotes = [];
        foreach ($symbols as $symbol) {
            $quotes[$symbol] = $this->quote($symbol);
        }

        return $quotes;
    }

    public function candles(string $symbol, Timeframe $timeframe, int $limit = 100): array
    {
        $current = $this->quote($symbol)->priceCents;
        $base = $this->basePrice($symbol);
        $bucket = intdiv(now()->getTimestamp(), $timeframe->seconds());
        $closeOf = fn (int $b): int => max(100, (int) round(
            $base * (1 + 0.03 * sin($b / 17) + 0.004 * $this->noise($symbol, $b))
        ));

        $candles = [];
        for ($b = $bucket - $limit + 1; $b <= $bucket; $b++) {
            $close = $b === $bucket ? $current : $closeOf($b);
            $open = $closeOf($b - 1);
            $wick = (int) round($base * 0.002 * (1 + abs($this->noise($symbol, $b * 7))));
            $candles[] = new Candle(
                CarbonImmutable::createFromTimestamp($b * $timeframe->seconds()),
                $open,
                max($open, $close) + $wick,
                max(1, min($open, $close) - $wick),
                $close,
                (int) (10000 + 5000 * abs($this->noise($symbol, $b * 13))),
            );
        }

        return $candles;
    }

    public function marketClock(): MarketClock
    {
        return $this->calendar->clock();
    }

    public function isMarketOpen(): bool
    {
        return $this->calendar->isOpen();
    }

    private function key(string $symbol): string
    {
        return "fake_market:{$symbol}";
    }

    private function basePrice(string $symbol): int
    {
        $known = config('trading.fake_base_prices', []);

        return $known[$symbol] ?? 2000 + (crc32($symbol) % 48000);
    }

    /**
     * Stateloze pseudo-ruis in [-1, 1], zodat candles reproduceerbaar zijn.
     */
    private function noise(string $symbol, int $n): float
    {
        return (crc32($symbol.':'.$n) % 20001) / 10000 - 1;
    }
}
