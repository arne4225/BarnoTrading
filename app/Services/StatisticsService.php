<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Handelsstatistieken op basis van gesloten trades en dagelijkse snapshots.
 * Rekent in gehele centen; alleen verhoudingen (percentages, profit factor) zijn floats.
 */
class StatisticsService
{
    public const PERIODS = ['all' => 'Alles', 'month' => '30 dagen', 'week' => '7 dagen', 'day' => 'Vandaag'];

    /**
     * Alle statistieken voor een gebruiker, 60 seconden gecachet en automatisch ongeldig
     * zodra er een trade bijkomt of verandert.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, string $period = 'all'): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'all';
        $stamp = $user->trades()->selectRaw('count(*) as c, max(updated_at) as m')->first();
        $key = "stats:{$user->id}:{$period}:{$stamp->c}:{$stamp->m}:".$user->portfolioSnapshots()->max('updated_at');

        return Cache::remember($key, 60, function () use ($user, $period) {
            $trades = $user->trades()->whereNotNull('closed_at')
                ->when($this->periodStart($period), fn ($q, $from) => $q->where('closed_at', '>=', $from))
                ->orderBy('closed_at')->get();

            $curve = $this->equityCurve($user);
            $drawdown = $this->maxDrawdown(array_column($curve, 'equity_cents'));

            return [
                'summary' => $this->summarize($trades),
                'max_drawdown_cents' => $drawdown['cents'],
                'max_drawdown_percent' => $drawdown['percent'],
                'by_tag' => $this->byTag($trades),
                'by_weekday' => $this->byWeekday($trades),
                'by_hour' => $this->byHour($trades),
                'equity_curve' => $curve,
            ];
        });
    }

    /**
     * @param  Collection<int, Trade>  $trades  gesloten trades
     * @return array<string, mixed>
     */
    public function summarize(Collection $trades): array
    {
        $wins = $trades->filter(fn (Trade $t) => $t->realized_pnl_cents > 0);
        $losses = $trades->filter(fn (Trade $t) => $t->realized_pnl_cents < 0);
        $count = $trades->count();

        $grossProfit = (int) $wins->sum('realized_pnl_cents');
        $grossLoss = abs((int) $losses->sum('realized_pnl_cents'));
        $total = (int) $trades->sum('realized_pnl_cents');

        $best = $trades->sortByDesc('realized_pnl_cents')->first();
        $worst = $trades->sortBy('realized_pnl_cents')->first();

        return [
            'count' => $count,
            'wins' => $wins->count(),
            'losses' => $losses->count(),
            'win_rate' => $count === 0 ? null : round($wins->count() / $count * 100, 1),
            'avg_win_cents' => $wins->isEmpty() ? null : intdiv($grossProfit, $wins->count()),
            'avg_loss_cents' => $losses->isEmpty() ? null : -intdiv($grossLoss, $losses->count()),
            'profit_factor' => $grossLoss === 0 ? null : round($grossProfit / $grossLoss, 2),
            'expectancy_cents' => $count === 0 ? null : intdiv($total, $count),
            'total_pnl_cents' => $total,
            'best' => $best ? $this->tradeSummary($best) : null,
            'worst' => $worst ? $this->tradeSummary($worst) : null,
        ];
    }

    /**
     * Grootste daling van piek naar dal in een equity-reeks.
     *
     * @param  list<int>  $equity
     * @return array{cents: int, percent: float}
     */
    public function maxDrawdown(array $equity): array
    {
        $peak = null;
        $maxCents = 0;
        $maxPercent = 0.0;

        foreach ($equity as $value) {
            $peak = $peak === null ? $value : max($peak, $value);
            $drop = $peak - $value;
            $maxCents = max($maxCents, $drop);
            if ($peak > 0) {
                $maxPercent = max($maxPercent, $drop / $peak * 100);
            }
        }

        return ['cents' => $maxCents, 'percent' => round($maxPercent, 2)];
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<string, array{count: int, pnl_cents: int, win_rate: float}>
     */
    public function byTag(Collection $trades): array
    {
        $groups = [];
        foreach ($trades as $trade) {
            foreach ($trade->tags ?? [] as $tag) {
                $groups[$tag][] = $trade;
            }
        }
        ksort($groups);

        return array_map(fn (array $group) => $this->group(collect($group)), $groups);
    }

    /**
     * Op opening-moment in Eastern Time; sleutels 1 (maandag) t/m 7 (zondag).
     *
     * @param  Collection<int, Trade>  $trades
     * @return array<int, array{count: int, pnl_cents: int, win_rate: float}>
     */
    public function byWeekday(Collection $trades): array
    {
        $groups = $trades->groupBy(fn (Trade $t) => $t->opened_at->setTimezone(config('trading.timezone'))->isoWeekday())->sortKeys();

        return $groups->map(fn (Collection $group) => $this->group($group))->all();
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<int, array{count: int, pnl_cents: int, win_rate: float}>
     */
    public function byHour(Collection $trades): array
    {
        $groups = $trades->groupBy(fn (Trade $t) => (int) $t->opened_at->setTimezone(config('trading.timezone'))->format('G'))->sortKeys();

        return $groups->map(fn (Collection $group) => $this->group($group))->all();
    }

    /**
     * Equity per dag uit de snapshots, met het startkapitaal als beginpunt.
     *
     * @return list<array{date: string, equity_cents: int, drawdown_percent: float}>
     */
    public function equityCurve(User $user): array
    {
        $snapshots = $user->portfolioSnapshots()->orderBy('date')->get(['date', 'equity_cents']);
        if ($snapshots->isEmpty()) {
            return [];
        }

        $points = [[
            'date' => CarbonImmutable::parse($snapshots->first()->date)->subDay()->toDateString(),
            'equity_cents' => $user->starting_cash_cents,
        ]];
        foreach ($snapshots as $snapshot) {
            $points[] = ['date' => substr((string) $snapshot->date, 0, 10), 'equity_cents' => $snapshot->equity_cents];
        }

        $peak = 0;

        return array_map(function (array $point) use (&$peak) {
            $peak = max($peak, $point['equity_cents']);
            $point['drawdown_percent'] = $peak > 0 ? round(($point['equity_cents'] - $peak) / $peak * 100, 2) : 0.0;

            return $point;
        }, $points);
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array{count: int, pnl_cents: int, win_rate: float}
     */
    private function group(Collection $trades): array
    {
        return [
            'count' => $trades->count(),
            'pnl_cents' => (int) $trades->sum('realized_pnl_cents'),
            'win_rate' => round($trades->filter(fn (Trade $t) => $t->realized_pnl_cents > 0)->count() / $trades->count() * 100, 1),
        ];
    }

    /**
     * @return array{symbol: string, pnl_cents: int, closed_at: string}
     */
    private function tradeSummary(Trade $trade): array
    {
        return ['symbol' => $trade->symbol, 'pnl_cents' => $trade->realized_pnl_cents, 'closed_at' => $trade->closed_at->toDateTimeString()];
    }

    private function periodStart(string $period): ?CarbonImmutable
    {
        $now = now(config('trading.timezone'))->toImmutable();

        return match ($period) {
            'day' => $now->startOfDay()->utc(),
            'week' => $now->subDays(7)->utc(),
            'month' => $now->subDays(30)->utc(),
            default => null,
        };
    }
}
