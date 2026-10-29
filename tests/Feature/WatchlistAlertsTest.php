<?php

use App\Models\PriceAlert;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\AlertService;
use App\Services\MarketData\FakeMarketDataProvider;
use App\Services\WatchlistService;

beforeEach(function () {
    $this->market = app(FakeMarketDataProvider::class)->freeze();
    $this->market->setPrice('AAPL', 10000);
    $this->market->setPrice('MSFT', 20000);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('watchlists', function () {
    it('creates a default list on first use and supports several lists', function () {
        $this->get('/watchlist')->assertOk()->assertSee('Standaard');

        $this->post('/watchlists', ['name' => 'Swingtrades'])->assertRedirect();
        $this->post('/watchlists', ['name' => 'Swingtrades'])->assertSessionHasErrors('name');

        expect($this->user->watchlists()->pluck('name')->all())->toBe(['Standaard', 'Swingtrades']);
    });

    it('adds symbols to the selected list only', function () {
        $default = app(WatchlistService::class)->defaultFor($this->user);
        $other = $this->user->watchlists()->create(['name' => 'Tech']);

        $this->post('/watchlist', ['symbol' => 'msft', 'watchlist_id' => $other->id])->assertSessionHas('status');
        $this->post('/watchlist', ['symbol' => 'AAPL'])->assertSessionHas('status');

        expect($other->items()->pluck('symbol')->all())->toBe(['MSFT'])
            ->and($default->items()->pluck('symbol')->all())->toBe(['AAPL']);

        $this->get("/watchlist?list={$other->id}")->assertSee('MSFT');
    });

    it('does not accept a list of another user', function () {
        $foreign = Watchlist::factory()->create();

        $this->post('/watchlist', ['symbol' => 'AAPL', 'watchlist_id' => $foreign->id])->assertSessionHasErrors('watchlist_id');
        $this->delete("/watchlists/{$foreign->id}")->assertForbidden();
    });

    it('keeps the last list and deletes others with their items', function () {
        $first = app(WatchlistService::class)->defaultFor($this->user);
        $this->delete("/watchlists/{$first->id}")->assertSessionHas('error');

        $second = $this->user->watchlists()->create(['name' => 'Tech']);
        $second->items()->create(['user_id' => $this->user->id, 'symbol' => 'AAPL']);
        $this->delete("/watchlists/{$second->id}")->assertRedirect('/watchlist');

        expect(Watchlist::find($second->id))->toBeNull()
            ->and($this->user->watchlistItems()->count())->toBe(0);
    });

    it('sorts rows by symbol, price and daily change', function () {
        $list = app(WatchlistService::class)->defaultFor($this->user);
        foreach (['MSFT', 'AAPL'] as $symbol) {
            $list->items()->create(['user_id' => $this->user->id, 'symbol' => $symbol]);
        }
        $service = app(WatchlistService::class);
        $symbols = fn (string $sort, string $dir) => collect($service->rows($list, $sort, $dir))->map(fn ($r) => $r['item']->symbol)->all();

        expect($symbols('symbol', 'asc'))->toBe(['AAPL', 'MSFT'])
            ->and($symbols('symbol', 'desc'))->toBe(['MSFT', 'AAPL'])
            ->and($symbols('price', 'desc'))->toBe(['MSFT', 'AAPL'])
            ->and($symbols('price', 'asc'))->toBe(['AAPL', 'MSFT'])
            ->and($symbols('bogus', 'asc'))->toBe(['AAPL', 'MSFT']);
    });

    it('provides a sparkline and daily change for each row', function () {
        $list = app(WatchlistService::class)->defaultFor($this->user);
        $list->items()->create(['user_id' => $this->user->id, 'symbol' => 'AAPL']);

        $row = app(WatchlistService::class)->rows($list)[0];

        expect($row['sparkline'])->toHaveCount(30)
            ->and($row['change_percent'])->toBeFloat()
            ->and($row['quote']->priceCents)->toBe(10000);

        $this->get('/watchlist')->assertOk()->assertSee('<polyline', false);
    });
});

describe('price alerts', function () {
    it('derives the direction from the current price', function () {
        $up = app(AlertService::class)->create($this->user, 'aapl', 11000);
        $down = app(AlertService::class)->create($this->user, 'AAPL', 9000);

        expect($up->direction)->toBe('above')->and($down->direction)->toBe('below');
    });

    it('rejects an alert at the current price', function () {
        $this->post('/alerts', ['symbol' => 'AAPL', 'price' => '100'])->assertSessionHas('error');

        expect(PriceAlert::count())->toBe(0);
    });

    it('triggers when the price touches the level, once', function () {
        $above = app(AlertService::class)->create($this->user, 'AAPL', 10500);
        $below = app(AlertService::class)->create($this->user, 'MSFT', 19000);

        expect(app(AlertService::class)->check($this->user))->toBe(0);

        $this->market->setPrice('AAPL', 10500);
        $this->market->setPrice('MSFT', 18900);

        expect(app(AlertService::class)->check($this->user))->toBe(2)
            ->and($above->refresh()->triggered_price_cents)->toBe(10500)
            ->and($below->refresh()->triggered_price_cents)->toBe(18900)
            ->and(app(AlertService::class)->check($this->user))->toBe(0);
    });

    it('shows a banner until the alert is acknowledged', function () {
        $alert = app(AlertService::class)->create($this->user, 'AAPL', 10500);
        $this->market->setPrice('AAPL', 10600);

        $this->get('/dashboard')->assertSee('raakte $105.00', false);

        $this->post("/alerts/{$alert->id}/acknowledge")->assertRedirect();

        $this->get('/dashboard')->assertDontSee('raakte $105.00', false);
    });

    it('only lets users manage their own alerts', function () {
        $theirs = PriceAlert::factory()->create();

        $this->delete("/alerts/{$theirs->id}")->assertForbidden();
        $this->post("/alerts/{$theirs->id}/acknowledge")->assertForbidden();
    });

    it('is checked by the order engine for all users', function () {
        $alert = app(AlertService::class)->create($this->user, 'AAPL', 10500);
        $this->market->setPrice('AAPL', 10500);

        $this->artisan('trading:engine', ['--once' => true])->expectsOutputToContain('1 alert(s) afgegaan')->assertSuccessful();

        expect($alert->refresh()->isTriggered())->toBeTrue();
    });
});
