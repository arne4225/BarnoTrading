<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Services\MarketData\FakeMarketDataProvider;

beforeEach(function () {
    $this->market = app(FakeMarketDataProvider::class)->freeze();
    $this->market->setPrice('AAPL', 10000);
    $this->user = User::factory()->create(['starting_cash_cents' => 1_000_000, 'cash_cents' => 1_000_000]);
    $this->actingAs($this->user);
});

it('redirects guests to the login page', function () {
    auth()->logout();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->post('/orders')->assertRedirect('/login');
});

it('gives new users the configured starting capital', function () {
    auth()->logout();

    $this->post('/register', [
        'name' => 'Nieuwe Trader', 'email' => 'nieuw@example.com',
        'password' => 'password-123', 'password_confirmation' => 'password-123',
    ]);

    $user = User::firstWhere('email', 'nieuw@example.com');
    expect($user->cash_cents)->toBe(10_000_000)->and($user->starting_cash_cents)->toBe(10_000_000);
});

it('renders all main pages', function (string $url) {
    $this->get($url)->assertOk();
})->with(['/dashboard', '/stocks', '/stocks?q=tesla', '/stocks/AAPL', '/orders', '/transactions', '/watchlist', '/account']);

it('shows the market status and the delay of quotes', function () {
    $this->get('/stocks/AAPL')
        ->assertSee('Markt open')
        ->assertSee('Gesimuleerde koersen')
        ->assertSee('realtime');
});

it('returns 404 for an invalid symbol', function () {
    $this->get('/stocks/NOT-VALID')->assertNotFound();
});

it('serves a json quote', function () {
    $this->getJson('/stocks/AAPL/quote')->assertOk()->assertJson(['price' => '$100.00', 'market_open' => true]);
});

it('places a market order through the form and shows it on the dashboard', function () {
    $this->post('/orders', [
        'symbol' => 'aapl', 'side' => 'buy', 'type' => 'market', 'quantity' => 5,
    ])->assertRedirect()->assertSessionHas('status');

    $this->get('/dashboard')->assertSee('AAPL')->assertSee('$9,500.00', false);
    expect($this->user->refresh()->cash_cents)->toBe(950_000);
});

it('places a bracket limit order with decimal comma input', function () {
    $this->post('/orders', [
        'symbol' => 'AAPL', 'side' => 'buy', 'type' => 'limit', 'quantity' => 2,
        'limit_price' => '95,50', 'take_profit' => '110.00', 'stop_loss' => '90',
    ])->assertSessionHas('status');

    $order = Order::whereNull('parent_order_id')->first();
    expect($order->limit_price_cents)->toBe(9550)
        ->and($order->children)->toHaveCount(2)
        ->and($order->status)->toBe(OrderStatus::Pending);
});

it('shows a clear error for invalid orders instead of crashing', function () {
    $this->from('/stocks/AAPL')->post('/orders', [
        'symbol' => 'AAPL', 'side' => 'sell', 'type' => 'market', 'quantity' => 1,
    ])->assertRedirect('/stocks/AAPL')->assertSessionHas('error');

    $this->post('/orders', ['symbol' => 'AAPL', 'side' => 'buy', 'type' => 'market', 'quantity' => 0])
        ->assertSessionHasErrors('quantity');
});

it('lets users cancel only their own open orders', function () {
    $mine = Order::factory()->for($this->user)->create(['status' => OrderStatus::Pending]);
    $theirs = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->delete("/orders/{$theirs->id}")->assertForbidden();
    $this->delete("/orders/{$mine->id}")->assertRedirect();

    expect($mine->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($theirs->refresh()->status)->toBe(OrderStatus::Pending);
});

it('manages the watchlist per user', function () {
    $this->post('/watchlist', ['symbol' => 'nvda'])->assertSessionHas('status');
    $this->post('/watchlist', ['symbol' => 'NVDA']);
    $other = WatchlistItem::factory()->create(['symbol' => 'TSLA']);

    expect($this->user->watchlistItems()->count())->toBe(1);
    $this->get('/watchlist')->assertSee('NVDA')->assertDontSee('TSLA');
    $this->delete("/watchlist/{$other->id}")->assertForbidden();
    $this->delete('/watchlist/'.$this->user->watchlistItems()->first()->id)->assertRedirect();
    expect($this->user->watchlistItems()->count())->toBe(0);
});

it('resets the account with a new starting capital', function () {
    $this->post('/orders', ['symbol' => 'AAPL', 'side' => 'buy', 'type' => 'market', 'quantity' => 5]);

    $this->post('/account/reset', ['starting_cash' => 50000, 'confirm' => 1])->assertRedirect('/dashboard');

    expect($this->user->refresh()->cash_cents)->toBe(5_000_000)
        ->and($this->user->positions()->count())->toBe(0);
});

it('requires confirmation to reset', function () {
    $this->post('/account/reset', ['starting_cash' => 50000])->assertSessionHasErrors('confirm');
});
