<?php

use App\Contracts\MarketDataProvider;
use App\Enums\Timeframe;
use App\Services\MarketData\AlpacaMarketDataProvider;
use App\Services\MarketData\FakeMarketDataProvider;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('uses the fake provider in tests', function () {
    expect(app(MarketDataProvider::class))->toBeInstanceOf(FakeMarketDataProvider::class);
});

it('returns controllable frozen prices from the fake provider', function () {
    $fake = app(FakeMarketDataProvider::class)->freeze()->setPrice('AAPL', 19000);

    expect($fake->quote('AAPL')->priceCents)->toBe(19000)
        ->and($fake->quote('AAPL')->delayLabel())->toBe('realtime');
});

it('returns ordered candles ending at the current price', function () {
    $fake = app(FakeMarketDataProvider::class)->freeze()->setPrice('MSFT', 42000);

    $candles = $fake->candles('MSFT', Timeframe::FiveMinutes, 30);

    expect($candles)->toHaveCount(30)
        ->and(end($candles)->closeCents)->toBe(42000)
        ->and($candles[0]->time->lessThan($candles[29]->time))->toBeTrue()
        ->and($candles[5]->highCents)->toBeGreaterThanOrEqual($candles[5]->lowCents);
});

describe('alpaca', function () {
    beforeEach(function () {
        config([
            'trading.market_data_driver' => 'alpaca',
            'trading.alpaca.key_id' => 'key',
            'trading.alpaca.secret_key' => 'secret',
        ]);
        Cache::flush();
    });

    it('maps snapshots to quotes in cents and sends auth headers', function () {
        Http::fake(['data.alpaca.markets/*' => Http::response([
            'AAPL' => [
                'latestTrade' => ['p' => 190.55, 't' => now()->subSeconds(30)->toIso8601String()],
                'latestQuote' => ['bp' => 190.5, 'ap' => 190.6],
            ],
        ])]);

        $quote = app(AlpacaMarketDataProvider::class)->quote('AAPL');

        expect($quote->priceCents)->toBe(19055)
            ->and($quote->bidCents)->toBe(19050)
            ->and($quote->askCents)->toBe(19060)
            ->and($quote->delaySeconds())->toBeBetween(29, 35);
        Http::assertSent(fn ($r) => $r->hasHeader('APCA-API-KEY-ID', 'key') && $r['feed'] === 'iex');
    });

    it('only talks to the market data host', function () {
        Http::fake(['*' => Http::response([])]);

        app(AlpacaMarketDataProvider::class)->candles('AAPL', Timeframe::OneDay, 5);

        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://data.alpaca.markets/'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'paper-api') || str_contains($r->url(), '/orders'));
    });

    it('caches quotes between calls', function () {
        Http::fake(['*' => Http::response(['AAPL' => ['latestTrade' => ['p' => 100, 't' => now()->toIso8601String()]]])]);
        $provider = app(AlpacaMarketDataProvider::class);

        $provider->quote('AAPL');
        $provider->quote('AAPL');

        Http::assertSentCount(1);
    });

    it('returns candles oldest first', function () {
        Http::fake(['*' => Http::response(['bars' => [
            ['t' => '2026-10-29T15:00:00Z', 'o' => 2, 'h' => 3, 'l' => 1, 'c' => 2.5, 'v' => 10],
            ['t' => '2026-10-29T14:00:00Z', 'o' => 1, 'h' => 2, 'l' => 1, 'c' => 2, 'v' => 5],
        ]])]);

        $candles = app(AlpacaMarketDataProvider::class)->candles('AAPL', Timeframe::OneHour, 2);

        expect($candles[0]->openCents)->toBe(100)->and($candles[1]->closeCents)->toBe(250);
    });

    it('falls back to the last known quote on rate limiting', function () {
        Http::fake(['*' => Http::sequence()
            ->push(['AAPL' => ['latestTrade' => ['p' => 150, 't' => now()->toIso8601String()]]])
            ->push([], 429)]);
        $service = app(MarketDataService::class);

        $service->quote('AAPL');
        Cache::forget('alpaca:quote:AAPL');
        $stale = $service->quote('AAPL');

        expect($stale->isStale)->toBeTrue()
            ->and($stale->priceCents)->toBe(15000)
            ->and($service->error())->toContain('rate limit');
    });

    it('reports a clear error instead of crashing without keys', function () {
        config(['trading.alpaca.key_id' => null]);
        $service = app(MarketDataService::class);

        expect($service->quotes(['AAPL']))->toBe([])
            ->and($service->error())->toContain('ALPACA_KEY_ID');
    });
});

it('finds symbols by ticker or name', function () {
    $service = app(MarketDataService::class);

    expect($service->search('appl'))->toHaveKey('AAPL')
        ->and($service->search('tesla'))->toHaveKey('TSLA')
        ->and($service->search(''))->toBe([]);
});
