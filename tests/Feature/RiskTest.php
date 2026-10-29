<?php

use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Exceptions\InvalidOrderException;
use App\Models\User;
use App\Services\MarketData\FakeMarketDataProvider;
use App\Services\OrderService;
use App\Services\RiskService;

beforeEach(function () {
    $this->market = app(FakeMarketDataProvider::class)->freeze();
    $this->market->setPrice('AAPL', 10000);
    $this->user = User::factory()->create(['starting_cash_cents' => 1_000_000, 'cash_cents' => 1_000_000]);
    $this->risk = app(RiskService::class);
    $this->orders = app(OrderService::class);
});

function buy(int $qty, array $extra = []): PlaceOrderData
{
    return new PlaceOrderData(...['symbol' => 'AAPL', 'side' => OrderSide::Buy, 'type' => OrderType::Market, 'quantity' => $qty, ...$extra]);
}

describe('warnings', function () {
    it('warns about an order without stop-loss', function () {
        expect($this->risk->assess($this->user, buy(5)))->toContain('Deze order heeft geen stop-loss: je verlies is onbegrensd.');
    });

    it('does not warn about the stop-loss when disabled or present', function () {
        expect($this->risk->assess($this->user, buy(5, ['stopLossCents' => 9500])))->toBe([]);

        $this->user->update(['warn_without_stop_loss' => false]);
        expect($this->risk->assess($this->user, buy(5)))->toBe([]);
    });

    it('warns when risk/reward is below the personal minimum', function () {
        $this->user->update(['min_risk_reward_hundredths' => 200]);

        $warnings = $this->risk->assess($this->user, buy(5, ['stopLossCents' => 9500, 'takeProfitCents' => 10750]));

        expect($warnings)->toHaveCount(1)->and($warnings[0])->toContain('1:1.50')->toContain('1:2.00');
    });

    it('accepts risk/reward at or above the minimum', function () {
        expect($this->risk->assess($this->user, buy(5, ['stopLossCents' => 9500, 'takeProfitCents' => 10500])))->toBe([]);
    });

    it('warns when the position is larger than the allowed percentage of the account', function () {
        $warnings = $this->risk->assess($this->user, buy(30, ['stopLossCents' => 9900])); // $3.000 = 30%

        expect($warnings)->toHaveCount(1)->and($warnings[0])->toContain('30.0%')->toContain('25%');
    });

    it('includes the existing position in the size check', function () {
        $this->orders->place($this->user, buy(20, ['stopLossCents' => 9900]));

        expect($this->risk->assess($this->user, buy(10, ['stopLossCents' => 9900])))->toHaveCount(1);
    });

    it('never warns about orders that reduce a position', function () {
        $this->orders->place($this->user, buy(5));

        $sell = new PlaceOrderData('AAPL', OrderSide::Sell, OrderType::Market, 5);

        expect($this->risk->assess($this->user, $sell))->toBe([]);
    });

    it('shows warnings after placing an order via the form without blocking it', function () {
        $this->actingAs($this->user)
            ->post('/orders', ['symbol' => 'AAPL', 'side' => 'buy', 'type' => 'market', 'quantity' => 5])
            ->assertSessionHas('status')
            ->assertSessionHas('warnings', fn (array $w) => count($w) === 1);
    });
});

describe('daily loss limit', function () {
    beforeEach(function () {
        $this->user->update(['daily_loss_limit_bps' => 300]); // 3% van $10.000 = $300
        $this->user->portfolioSnapshots()->create([
            'date' => now(config('trading.timezone'))->subDay()->toDateString(),
            'equity_cents' => 1_000_000, 'cash_cents' => 1_000_000,
        ]);
    });

    it('allows new orders while the loss stays under the limit', function () {
        $this->orders->place($this->user, buy(50));
        $this->market->setPrice('AAPL', 9900); // -$50

        expect($this->orders->place($this->user, buy(1))->status->value)->toBe('filled');
    });

    it('blocks new orders after the limit is hit but still allows closing', function () {
        $this->orders->place($this->user, buy(50));
        $this->market->setPrice('AAPL', 9000); // -$500 = 5%

        expect(fn () => $this->orders->place($this->user, buy(1)))->toThrow(InvalidOrderException::class, 'verlieslimiet');

        $short = new PlaceOrderData('AAPL', OrderSide::Sell, OrderType::Market, 1, isShort: true);
        expect(fn () => $this->orders->place($this->user, $short))->toThrow(InvalidOrderException::class, 'verlieslimiet');

        $close = new PlaceOrderData('AAPL', OrderSide::Sell, OrderType::Market, 50);
        expect($this->orders->place($this->user, $close)->status->value)->toBe('filled');
    });

    it('stays blocked for the rest of the day, even after recovering', function () {
        $this->orders->place($this->user, buy(50));
        $this->market->setPrice('AAPL', 9000);
        try {
            $this->orders->place($this->user, buy(1));
        } catch (InvalidOrderException) {
        }

        $this->market->setPrice('AAPL', 10000);

        expect(fn () => $this->orders->place($this->user->refresh(), buy(1)))->toThrow(InvalidOrderException::class, 'verlieslimiet');
    });

    it('unblocks on the next trading day', function () {
        $this->user->update(['trading_blocked_on' => now(config('trading.timezone'))->subDay()->toDateString()]);

        expect($this->orders->place($this->user, buy(1))->status->value)->toBe('filled');
    });

    it('does nothing when no limit is configured', function () {
        $this->user->update(['daily_loss_limit_bps' => null]);
        $this->orders->place($this->user, buy(50));
        $this->market->setPrice('AAPL', 5000);

        expect($this->orders->place($this->user, buy(1))->status->value)->toBe('filled');
    });
});

describe('risk preview endpoint', function () {
    beforeEach(fn () => $this->actingAs($this->user));

    it('calculates the position size with the personal risk percentage', function () {
        $this->getJson('/risk/preview?symbol=AAPL&side=buy&stop_loss=98')
            ->assertOk()
            ->assertJsonPath('sizing.shares', 50) // 1% van $10.000 = $100 / $2
            ->assertJsonPath('sizing.max_loss', 100)
            ->assertJsonPath('error', null);
    });

    it('accepts a custom risk percentage, target and entry price', function () {
        $this->getJson('/risk/preview?symbol=AAPL&side=buy&entry=100&stop_loss=95&take_profit=110&risk_percent=0,5')
            ->assertJsonPath('sizing.shares', 10)
            ->assertJsonPath('sizing.risk_reward', 2)
            ->assertJsonPath('sizing.potential_profit', 100);
    });

    it('returns warnings when a quantity is given', function () {
        $this->getJson('/risk/preview?symbol=AAPL&side=buy&quantity=30')
            ->assertJsonCount(2, 'warnings');
    });

    it('returns an error message for an invalid stop-loss', function () {
        $this->getJson('/risk/preview?symbol=AAPL&side=buy&stop_loss=100')
            ->assertOk()->assertJsonPath('sizing', null)->assertJsonPath('error', fn ($e) => str_contains($e, 'gelijk'));
    });

    it('validates its input and requires authentication', function () {
        $this->getJson('/risk/preview?symbol=AAPL&side=hold')->assertUnprocessable();

        auth()->logout();
        $this->getJson('/risk/preview?symbol=AAPL&side=buy')->assertUnauthorized();
    });
});
