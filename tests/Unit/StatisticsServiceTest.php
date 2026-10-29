<?php

use App\Models\Trade;
use App\Services\StatisticsService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

function tradeWith(int $pnl, array $attributes = []): Trade
{
    return new Trade([
        'symbol' => 'AAPL',
        'realized_pnl_cents' => $pnl,
        'opened_at' => CarbonImmutable::parse('2026-10-28 14:30', 'UTC'), // woensdag 10:30 ET
        'closed_at' => CarbonImmutable::parse('2026-10-28 15:30', 'UTC'),
        ...$attributes,
    ]);
}

beforeEach(fn () => $this->stats = new StatisticsService);

it('calculates win rate, averages, profit factor and expectancy', function () {
    $trades = collect([tradeWith(10_000), tradeWith(20_000), tradeWith(-5_000), tradeWith(-15_000)]);

    $summary = $this->stats->summarize($trades);

    expect($summary['count'])->toBe(4)
        ->and($summary['win_rate'])->toBe(50.0)
        ->and($summary['avg_win_cents'])->toBe(15_000)
        ->and($summary['avg_loss_cents'])->toBe(-10_000)
        ->and($summary['profit_factor'])->toBe(1.5)
        ->and($summary['expectancy_cents'])->toBe(2_500)
        ->and($summary['total_pnl_cents'])->toBe(10_000)
        ->and($summary['best']['pnl_cents'])->toBe(20_000)
        ->and($summary['worst']['pnl_cents'])->toBe(-15_000);
});

it('handles empty and one-sided trade lists without dividing by zero', function () {
    $empty = $this->stats->summarize(collect());
    $onlyWins = $this->stats->summarize(collect([tradeWith(5_000)]));

    expect($empty['win_rate'])->toBeNull()
        ->and($empty['expectancy_cents'])->toBeNull()
        ->and($empty['best'])->toBeNull()
        ->and($onlyWins['profit_factor'])->toBeNull()
        ->and($onlyWins['avg_loss_cents'])->toBeNull()
        ->and($onlyWins['win_rate'])->toBe(100.0);
});

it('calculates the maximum drawdown from peak to trough', function () {
    expect($this->stats->maxDrawdown([100, 120, 90, 110, 80, 130]))->toBe(['cents' => 40, 'percent' => 33.33])
        ->and($this->stats->maxDrawdown([100, 110, 120]))->toBe(['cents' => 0, 'percent' => 0.0])
        ->and($this->stats->maxDrawdown([]))->toBe(['cents' => 0, 'percent' => 0.0]);
});

it('groups results per tag, counting multi-tag trades in every group', function () {
    $trades = collect([
        tradeWith(10_000, ['tags' => ['breakout', 'plan']]),
        tradeWith(-4_000, ['tags' => ['breakout']]),
        tradeWith(-9_000, ['tags' => ['fomo']]),
        tradeWith(1_000),
    ]);

    $byTag = $this->stats->byTag($trades);

    expect(array_keys($byTag))->toBe(['breakout', 'fomo', 'plan'])
        ->and($byTag['breakout'])->toBe(['count' => 2, 'pnl_cents' => 6_000, 'win_rate' => 50.0])
        ->and($byTag['fomo']['pnl_cents'])->toBe(-9_000);
});

it('groups per weekday and hour in Eastern Time', function () {
    $trades = collect([
        tradeWith(100), // woensdag 10:30 ET
        tradeWith(-50, ['opened_at' => CarbonImmutable::parse('2026-10-30 19:10', 'UTC')]), // vrijdag 15:10 ET
    ]);

    expect(array_keys($this->stats->byWeekday($trades)))->toBe([3, 5])
        ->and(array_keys($this->stats->byHour($trades)))->toBe([10, 15])
        ->and($this->stats->byHour($trades)[15]['pnl_cents'])->toBe(-50);
});
