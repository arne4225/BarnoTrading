<?php

namespace App\Services;

use App\Data\Candle;
use App\Enums\Timeframe;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\Facades\Cache;

/**
 * Bouwt de JSON voor de candlestick-grafiek: candles + door PHP berekende
 * indicatoren. Tijden worden met de Eastern Time-offset verschoven, zodat
 * Lightweight Charts (dat alleen UTC kent) beurstijd toont.
 */
class ChartDataService
{
    private const CACHE_SECONDS = 5;

    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly IndicatorService $indicators,
    ) {}

    /**
     * @param  array<string, mixed>  $indicatorConfig
     * @return array<string, mixed>
     */
    public function build(string $symbol, Timeframe $timeframe, array $indicatorConfig = [], int $limit = 300): array
    {
        $key = 'chart:'.md5(json_encode([$symbol, $timeframe->value, $indicatorConfig, $limit]));

        $payload = Cache::get($key);
        if ($payload !== null) {
            return $payload;
        }

        $candles = $this->marketData->candles($symbol, $timeframe, $limit);
        $payload = [
            'symbol' => $symbol,
            'timeframe' => $timeframe->value,
            'candles' => $this->candleSeries($candles),
            'indicators' => $this->indicatorSeries($candles, $indicatorConfig),
            'last_price' => $candles === [] ? null : end($candles)->closeCents / 100,
            'error' => $this->marketData->error(),
        ];

        if ($payload['error'] === null) {
            Cache::put($key, $payload, self::CACHE_SECONDS);
        }

        return $payload;
    }

    /**
     * @param  list<Candle>  $candles
     * @return list<array{time: int, open: float, high: float, low: float, close: float}>
     */
    private function candleSeries(array $candles): array
    {
        return array_map(fn (Candle $c) => [
            'time' => $this->chartTime($c),
            'open' => $c->openCents / 100,
            'high' => $c->highCents / 100,
            'low' => $c->lowCents / 100,
            'close' => $c->closeCents / 100,
        ], $candles);
    }

    /**
     * @param  list<Candle>  $candles
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function indicatorSeries(array $candles, array $config): array
    {
        $times = array_map(fn (Candle $c) => $this->chartTime($c), $candles);
        $line = fn (array $values): array => $this->pairs($times, $values);
        $output = [];

        foreach ($this->indicators->compute($candles, $config) as $name => $series) {
            $output[$name] = match (true) {
                $name === 'volume' => $this->volumeSeries($candles, $times),
                $name === 'macd' => [
                    'macd' => $line($series['macd']),
                    'signal' => $line($series['signal']),
                    'histogram' => $this->histogram($times, $series['histogram']),
                ],
                default => $line($series),
            };
        }

        return $output;
    }

    /**
     * @param  list<int>  $times
     * @param  list<int|float|null>  $values
     * @return list<array{time: int, value: float}>
     */
    private function pairs(array $times, array $values): array
    {
        $pairs = [];
        foreach ($values as $i => $value) {
            if ($value !== null) {
                $pairs[] = ['time' => $times[$i], 'value' => round($value, 4)];
            }
        }

        return $pairs;
    }

    /**
     * @param  list<int>  $times
     * @param  list<?float>  $values
     * @return list<array{time: int, value: float, color: string}>
     */
    private function histogram(array $times, array $values): array
    {
        return array_map(
            fn (array $p) => $p + ['color' => $p['value'] >= 0 ? '#34d399' : '#f87171'],
            $this->pairs($times, $values),
        );
    }

    /**
     * @param  list<Candle>  $candles
     * @param  list<int>  $times
     * @return list<array{time: int, value: int, color: string}>
     */
    private function volumeSeries(array $candles, array $times): array
    {
        return array_map(fn (Candle $c, int $time) => [
            'time' => $time,
            'value' => $c->volume,
            'color' => $c->closeCents >= $c->openCents ? 'rgba(52,211,153,0.4)' : 'rgba(248,113,113,0.4)',
        ], $candles, $times);
    }

    private function chartTime(Candle $candle): int
    {
        $eastern = $candle->time->setTimezone(config('trading.timezone'));

        return $candle->time->getTimestamp() + $eastern->utcOffset() * 60;
    }
}
