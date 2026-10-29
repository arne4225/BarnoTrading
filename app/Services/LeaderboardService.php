<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Rendement per periode voor gebruikers die zichzelf zichtbaar hebben gemaakt.
 * Alleen percentages en aantallen worden getoond, nooit absolute bedragen.
 */
class LeaderboardService
{
    public const PERIODS = ['day' => 'Dag', 'week' => 'Week', 'month' => 'Maand', 'all' => 'Totaal'];

    private const CACHE_SECONDS = 60;

    public function __construct(private readonly PortfolioService $portfolio) {}

    /**
     * @return list<array{rank: int, user_id: int, name: string, return_bps: int, closed_trades: int}>
     */
    public function ranking(string $period = 'all'): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'all';

        return Cache::remember("leaderboard:{$period}", self::CACHE_SECONDS, function () use ($period) {
            $start = $this->periodStart($period);
            $users = User::query()->where('leaderboard_visible', true)->get();

            $tradeCounts = $users->isEmpty() ? collect() : Trade::query()
                ->whereIn('user_id', $users->modelKeys())->whereNotNull('closed_at')
                ->when($start, fn ($q) => $q->where('closed_at', '>=', $start->startOfDay()->utc()))
                ->selectRaw('user_id, count(*) as c')->groupBy('user_id')->pluck('c', 'user_id');

            $rows = $users->map(fn (User $user) => [
                'user_id' => $user->id,
                'name' => $user->name,
                'return_bps' => $this->returnBps($user, $start),
                'closed_trades' => (int) ($tradeCounts[$user->id] ?? 0),
            ])->sortByDesc('return_bps')->values();

            return $rows->map(fn (array $row, int $i) => ['rank' => $i + 1] + $row)->all();
        });
    }

    /**
     * Rendement in basispunten t.o.v. de equity vlak voor de periode (of het startkapitaal).
     */
    private function returnBps(User $user, ?CarbonImmutable $start): int
    {
        $equity = $this->portfolio->summary($user)->equityCents;

        $baseline = $start === null
            ? $user->starting_cash_cents
            : ($user->portfolioSnapshots()->where('date', '<', $start->toDateString())->latest('date')->value('equity_cents')
                ?? $user->starting_cash_cents);

        return $baseline > 0 ? intdiv(($equity - $baseline) * 10000, $baseline) : 0;
    }

    private function periodStart(string $period): ?CarbonImmutable
    {
        $today = now(config('trading.timezone'))->toImmutable()->startOfDay();

        return match ($period) {
            'day' => $today,
            'week' => $today->subDays(7),
            'month' => $today->subDays(30),
            default => null,
        };
    }
}
