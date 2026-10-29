<?php

use App\Models\Trade;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Support\Facades\Cache;

/**
 * Gebruiker zonder posities: equity = cash, dus rendement is eenvoudig te sturen.
 */
function trader(string $name, int $cashCents, bool $visible = true): User
{
    return User::factory()->create([
        'name' => $name, 'starting_cash_cents' => 1_000_000, 'cash_cents' => $cashCents, 'leaderboard_visible' => $visible,
    ]);
}

beforeEach(function () {
    $this->alice = trader('Alice', 1_100_000);   // +10%
    $this->bob = trader('Bob', 950_000);         // -5%
    $this->carol = trader('Carol', 1_500_000, visible: false); // +50%, verborgen
});

it('ranks visible users by total return and hides everyone else', function () {
    $ranking = app(LeaderboardService::class)->ranking('all');

    expect(array_column($ranking, 'name'))->toBe(['Alice', 'Bob'])
        ->and($ranking[0])->toMatchArray(['rank' => 1, 'return_bps' => 1000])
        ->and($ranking[1])->toMatchArray(['rank' => 2, 'return_bps' => -500]);
});

it('never exposes absolute amounts or emails', function () {
    $row = app(LeaderboardService::class)->ranking('all')[0];

    expect(array_keys($row))->toBe(['rank', 'user_id', 'name', 'return_bps', 'closed_trades']);

    $this->actingAs($this->alice)->get('/leaderboard')
        ->assertSee('Alice')->assertSee('+10.00%')
        ->assertDontSee($this->bob->email)->assertDontSee('Carol');
});

it('measures the day against the previous close', function () {
    $yesterday = now(config('trading.timezone'))->subDay()->toDateString();
    $this->alice->portfolioSnapshots()->create(['date' => $yesterday, 'equity_cents' => 1_090_000, 'cash_cents' => 1_090_000]);
    $this->bob->portfolioSnapshots()->create(['date' => $yesterday, 'equity_cents' => 900_000, 'cash_cents' => 900_000]);

    $ranking = app(LeaderboardService::class)->ranking('day');

    // Alice: 1.100.000 vs 1.090.000 = +0,91%; Bob: 950.000 vs 900.000 = +5,55%
    expect(array_column($ranking, 'name'))->toBe(['Bob', 'Alice'])
        ->and($ranking[0]['return_bps'])->toBe(555)
        ->and($ranking[1]['return_bps'])->toBe(91);
});

it('uses the starting capital when there is no earlier snapshot', function () {
    expect(app(LeaderboardService::class)->ranking('week')[0]['return_bps'])->toBe(1000);
});

it('counts the closed trades within the period', function () {
    Trade::factory()->for($this->alice)->count(2)->create(['closed_at' => now()->subHour()]);
    Trade::factory()->for($this->alice)->create(['closed_at' => now()->subDays(20)]);
    Trade::factory()->for($this->alice)->open()->create();

    $service = app(LeaderboardService::class);

    expect($service->ranking('all')[0]['closed_trades'])->toBe(3)
        ->and($service->ranking('week')[0]['closed_trades'])->toBe(2);
});

it('lets a user opt in and shows a hint when not listed', function () {
    $this->actingAs($this->carol)->get('/leaderboard')->assertOk()->assertSee('Jij staat hier niet op');

    $this->carol->update(['leaderboard_visible' => true]);
    Cache::flush();

    $this->actingAs($this->carol)->get('/leaderboard')->assertSee('Carol')->assertSee('(jij)');
});

it('falls back to the total period for unknown periods and requires login', function () {
    $this->actingAs($this->alice)->get('/leaderboard?period=bogus')->assertOk()->assertSee('Alice');

    auth()->logout();
    $this->get('/leaderboard')->assertRedirect('/login');
});
