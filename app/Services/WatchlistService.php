<?php

namespace App\Services;

use App\Data\Quote;
use App\Enums\Timeframe;
use App\Models\User;
use App\Models\Watchlist;
use App\Models\WatchlistItem;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\Facades\Cache;

class WatchlistService
{
    public const SORTS = ['symbol', 'price', 'change'];

    private const SPARKLINE_DAYS = 30;

    private const SPARKLINE_TTL = 300;

    public function __construct(private readonly MarketDataService $marketData) {}

    public function defaultFor(User $user): Watchlist
    {
        return $user->watchlists()->orderBy('id')->first()
            ?? $user->watchlists()->create(['name' => 'Standaard']);
    }

    /**
     * Rijen voor een lijst: koers, dagverandering, sparkline; gesorteerd.
     *
     * @return list<array{item: WatchlistItem, quote: ?Quote, change_percent: ?float, sparkline: list<float>}>
     */
    public function rows(Watchlist $watchlist, string $sort = 'symbol', string $direction = 'asc'): array
    {
        $items = $watchlist->items()->get();
        $quotes = $this->marketData->quotes($items->pluck('symbol')->all());

        $rows = $items->map(function ($item) use ($quotes) {
            $quote = $quotes[$item->symbol] ?? null;
            $closes = $this->dailyCloses($item->symbol);
            $previous = $this->previousClose($closes);

            return [
                'item' => $item,
                'quote' => $quote,
                'change_percent' => $quote && $previous ? round(($quote->priceCents - $previous) / $previous * 100, 2) : null,
                'sparkline' => array_map(fn (int $c) => $c / 100, array_column($closes, 'close')),
            ];
        });

        $sort = in_array($sort, self::SORTS, true) ? $sort : 'symbol';
        $key = match ($sort) {
            'price' => fn (array $r) => $r['quote']?->priceCents ?? PHP_INT_MIN,
            'change' => fn (array $r) => $r['change_percent'] ?? -INF,
            default => fn (array $r) => $r['item']->symbol,
        };

        return $rows->sortBy($key, SORT_REGULAR, $direction === 'desc')->values()->all();
    }

    /**
     * Dagslotkoersen (30 dagen), 5 minuten gecachet om Alpaca-calls te sparen.
     *
     * @return list<array{date: string, close: int}>
     */
    private function dailyCloses(string $symbol): array
    {
        $key = "watchlist:daily:{$symbol}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $closes = array_map(fn ($c) => [
            'date' => $c->time->setTimezone(config('trading.timezone'))->toDateString(),
            'close' => $c->closeCents,
        ], $this->marketData->candles($symbol, Timeframe::OneDay, self::SPARKLINE_DAYS));

        if ($closes !== []) {
            Cache::put($key, $closes, self::SPARKLINE_TTL);
        }

        return $closes;
    }

    /**
     * Slotkoers van de vorige handelsdag; de laatste dagbar kan van vandaag zijn.
     *
     * @param  list<array{date: string, close: int}>  $closes
     */
    private function previousClose(array $closes): ?int
    {
        if ($closes === []) {
            return null;
        }

        $today = now(config('trading.timezone'))->toDateString();
        $last = end($closes);
        if ($last['date'] === $today) {
            return count($closes) > 1 ? $closes[count($closes) - 2]['close'] : null;
        }

        return $last['close'];
    }
}
