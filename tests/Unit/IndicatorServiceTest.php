<?php

use App\Data\Candle;
use App\Services\IndicatorService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

// Koersreeksen uit het StockCharts-naslagwerk (EMA en RSI).
const STOCKCHARTS_CLOSES = [
    22.27, 22.19, 22.08, 22.17, 22.18, 22.13, 22.23, 22.43, 22.24, 22.29,
    22.15, 22.39, 22.38, 22.61, 23.36, 24.05, 23.75, 23.83, 23.95, 23.63,
];
const WILDER_CLOSES = [
    44.34, 44.09, 44.15, 43.61, 44.33, 44.83, 45.10, 45.42, 45.84, 46.08,
    45.89, 46.03, 45.61, 46.28, 46.28, 46.00, 46.03, 46.41, 46.22, 45.64,
];

beforeEach(fn () => $this->indicators = new IndicatorService);

it('calculates a simple moving average', function () {
    expect($this->indicators->sma([1, 2, 3, 4, 5], 3))->toEqual([null, null, 2, 3, 4]);
});

it('returns only nulls when there is too little data', function () {
    expect($this->indicators->sma([1, 2], 5))->toBe([null, null])
        ->and($this->indicators->ema([1, 2], 5))->toBe([null, null])
        ->and($this->indicators->rsi([1, 2], 14))->toBe([null, null]);
});

it('calculates an EMA seeded with the SMA (StockCharts reference)', function () {
    $ema = $this->indicators->ema(STOCKCHARTS_CLOSES, 10);

    expect($ema[8])->toBeNull()
        ->and($ema[9])->toEqualWithDelta(22.221, 0.001)
        ->and($ema[10])->toEqualWithDelta(22.208, 0.001)
        ->and($ema[11])->toEqualWithDelta(22.241, 0.001)
        ->and($ema[12])->toEqualWithDelta(22.266, 0.001)
        ->and($ema[13])->toEqualWithDelta(22.328, 0.001);
});

it('calculates Wilder RSI (StockCharts reference)', function () {
    $rsi = $this->indicators->rsi(WILDER_CLOSES, 14);

    expect($rsi[13])->toBeNull()
        ->and($rsi[14])->toEqualWithDelta(70.53, 0.1)
        ->and($rsi[15])->toEqualWithDelta(66.32, 0.1)
        ->and($rsi[16])->toEqualWithDelta(66.55, 0.1);
});

it('gives RSI 100 for a series that only rises', function () {
    expect($this->indicators->rsi(range(1, 20), 14)[19])->toBe(100.0);
});

it('calculates MACD as fast EMA minus slow EMA with a signal line', function () {
    $values = array_map(fn ($i) => 100 + sin($i / 5) * 10 + $i * 0.2, range(0, 79));
    $macd = $this->indicators->macd($values, 12, 26, 9);
    $fast = $this->indicators->ema($values, 12);
    $slow = $this->indicators->ema($values, 26);

    expect($macd['macd'][24])->toBeNull()
        ->and($macd['macd'][25])->toEqualWithDelta($fast[25] - $slow[25], 1e-9)
        ->and($macd['signal'][32])->toBeNull()
        ->and($macd['signal'][33])->not->toBeNull()
        ->and($macd['histogram'][50])->toEqualWithDelta($macd['macd'][50] - $macd['signal'][50], 1e-9)
        ->and($macd['macd'])->toHaveCount(80);
});

it('calculates VWAP per day from typical price and volume', function () {
    $day1 = CarbonImmutable::parse('2026-10-28 15:00', 'UTC');
    $day2 = CarbonImmutable::parse('2026-10-29 15:00', 'UTC');
    $candles = [
        new Candle($day1, 10000, 10200, 9800, 10000, 100),   // typical 100,00
        new Candle($day1->addMinutes(5), 10000, 10500, 10100, 10300, 300), // typical 103,00
        new Candle($day2, 20000, 20000, 20000, 20000, 50),   // nieuwe dag
    ];

    $vwap = $this->indicators->vwap($candles);

    expect($vwap[0])->toEqualWithDelta(100.0, 1e-9)
        ->and($vwap[1])->toEqualWithDelta((100 * 100 + 103 * 300) / 400, 1e-9)
        ->and($vwap[2])->toEqualWithDelta(200.0, 1e-9);
});

it('computes only the requested indicators with custom periods', function () {
    $candles = array_map(
        fn ($i) => new Candle(CarbonImmutable::createFromTimestamp(1_700_000_000 + $i * 300), 10000, 10100, 9900, 10000 + $i * 10, 1000),
        range(0, 59),
    );

    $result = $this->indicators->compute($candles, ['sma' => [5, 20], 'ema' => [9], 'rsi' => 7, 'volume' => true]);

    expect(array_keys($result))->toBe(['sma5', 'sma20', 'ema9', 'rsi', 'volume'])
        ->and($result['sma5'][4])->not->toBeNull()
        ->and($result['rsi'][7])->not->toBeNull()
        ->and($result['volume'][3])->toBe(1000);
});
