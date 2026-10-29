<?php

use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Models\User;
use App\Services\MarketData\FakeMarketDataProvider;
use App\Services\OrderService;

beforeEach(function () {
    app(FakeMarketDataProvider::class)->freeze()->setPrice('AAPL', 10000);
    $this->user = User::factory()->create(['starting_cash_cents' => 1_000_000, 'cash_cents' => 1_000_000]);
    $this->actingAs($this->user);
});

it('stores settings in cents, basis points and hundredths', function () {
    $this->put('/account', [
        'commission' => '1,50', 'slippage_percent' => '0.05',
        'risk_per_trade_percent' => '0.5', 'max_position_percent' => 30,
        'min_risk_reward' => '1.5', 'warn_without_stop_loss' => 1,
        'daily_loss_limit_percent' => '3', 'leaderboard_visible' => 1,
    ])->assertSessionHas('status');

    $user = $this->user->refresh();
    expect($user->commission_cents)->toBe(150)
        ->and($user->slippage_bps)->toBe(5)
        ->and($user->risk_per_trade_bps)->toBe(50)
        ->and($user->max_position_percent)->toBe(30)
        ->and($user->min_risk_reward_hundredths)->toBe(150)
        ->and($user->daily_loss_limit_bps)->toBe(300)
        ->and($user->leaderboard_visible)->toBeTrue();
});

it('clears optional settings and falls back to the defaults', function () {
    $this->user->update(['commission_cents' => 500, 'daily_loss_limit_bps' => 300]);

    $this->put('/account', [
        'commission' => '', 'slippage_percent' => '', 'risk_per_trade_percent' => '1',
        'max_position_percent' => 25, 'min_risk_reward' => '1', 'daily_loss_limit_percent' => '',
    ])->assertSessionHasNoErrors();

    $user = $this->user->refresh();
    expect($user->commission_cents)->toBeNull()
        ->and($user->daily_loss_limit_bps)->toBeNull()
        ->and($user->effectiveCommissionCents())->toBe(config('trading.commission_cents'));
});

it('validates settings input', function () {
    $this->put('/account', [
        'commission' => 'abc', 'risk_per_trade_percent' => '', 'max_position_percent' => 0, 'min_risk_reward' => 'x',
    ])->assertSessionHasErrors(['commission', 'risk_per_trade_percent', 'max_position_percent', 'min_risk_reward']);
});

it('uses the personal commission and slippage when executing orders', function () {
    $this->user->update(['commission_cents' => 250, 'slippage_bps' => 100]); // 1%

    $order = app(OrderService::class)->place($this->user, new PlaceOrderData('AAPL', OrderSide::Buy, OrderType::Market, 10));

    expect($order->fill_price_cents)->toBe(10100)
        ->and($order->commission_cents)->toBe(250)
        ->and($this->user->refresh()->cash_cents)->toBe(1_000_000 - 101_000 - 250);
});

it('shows the settings page', function () {
    $this->get('/account')->assertOk()->assertSee('Commissie per order')->assertSee('Toon mij op het leaderboard');
});
