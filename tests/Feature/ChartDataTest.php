<?php

use App\Models\User;
use App\Services\MarketData\FakeMarketDataProvider;
use Carbon\CarbonImmutable;

beforeEach(function () {
    app(FakeMarketDataProvider::class)->freeze()->setPrice('AAPL', 19000);
    $this->actingAs(User::factory()->create());
});

it('returns candles for the requested timeframe', function () {
    $response = $this->getJson('/stocks/AAPL/chart?tf=15Min')->assertOk();

    expect($response->json('timeframe'))->toBe('15Min')
        ->and($response->json('candles'))->toHaveCount(300)
        ->and($response->json('last_price'))->toEqual(190)
        ->and($response->json('candles.299.close'))->toEqual(190)
        ->and($response->json('indicators'))->toBe([]);
});

it('computes the requested indicators with custom periods in PHP', function () {
    $response = $this->getJson('/stocks/AAPL/chart?sma=10,30&ema=9&rsi=7&macd=12,26,9&vwap=1&volume=1')->assertOk();

    expect(array_keys($response->json('indicators')))->toEqualCanonicalizing(['sma10', 'sma30', 'ema9', 'rsi', 'macd', 'vwap', 'volume'])
        ->and($response->json('indicators.sma10'))->toHaveCount(291)
        ->and($response->json('indicators.sma30'))->toHaveCount(271)
        ->and($response->json('indicators.rsi'))->toHaveCount(293)
        ->and($response->json('indicators.macd'))->toHaveKeys(['macd', 'signal', 'histogram'])
        ->and($response->json('indicators.volume'))->toHaveCount(300);
});

it('shifts chart times to Eastern Time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 15:00:00', 'UTC')); // EDT: UTC-4

    $last = $this->getJson('/stocks/AAPL/chart?tf=1Day')->json('candles.299.time');

    expect($last % 86400)->toBe((0 - 4 * 3600 + 86400) % 86400);
});

it('rejects invalid indicator input', function (string $query) {
    $this->getJson("/stocks/AAPL/chart?{$query}")->assertUnprocessable();
})->with(['tf=2Week', 'sma=abc', 'rsi=1', 'macd=12,26']);

it('requires authentication and a valid symbol', function () {
    $this->getJson('/stocks/NOT-VALID/chart')->assertNotFound();

    auth()->logout();
    $this->getJson('/stocks/AAPL/chart')->assertUnauthorized();
});

it('renders the chart container on the stock page', function () {
    $this->get('/stocks/AAPL')->assertOk()->assertSee('stockChart', false)->assertSee('stocks\\/AAPL\\/chart', false);
});
