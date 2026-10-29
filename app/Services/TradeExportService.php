<?php

namespace App\Services;

use App\Models\Trade;
use App\Support\Money;
use Illuminate\Support\LazyCollection;

class TradeExportService
{
    public const HEADER = [
        'id', 'symbol', 'direction', 'status', 'opened_at_et', 'closed_at_et', 'quantity',
        'avg_entry', 'avg_exit', 'commission', 'realized_pnl', 'self_rating', 'tags', 'entry_reason', 'note',
    ];

    /**
     * Schrijft trades als CSV naar een stream.
     *
     * @param  resource  $stream
     * @param  iterable<Trade>|LazyCollection<int, Trade>  $trades
     */
    public function write($stream, iterable $trades): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADER, ',', '"', '\\');

        foreach ($trades as $trade) {
            fputcsv($stream, [
                $trade->id,
                $trade->symbol,
                $trade->direction,
                $trade->isClosed() ? 'closed' : 'open',
                $trade->opened_at->setTimezone(config('trading.timezone'))->format('Y-m-d H:i:s'),
                $trade->closed_at?->setTimezone(config('trading.timezone'))->format('Y-m-d H:i:s'),
                $trade->peak_quantity,
                Money::decimal($trade->averageEntryCents()),
                $trade->averageExitCents() === null ? '' : Money::decimal($trade->averageExitCents()),
                Money::decimal($trade->commission_cents),
                Money::decimal($trade->realized_pnl_cents),
                $trade->self_rating,
                implode('|', $trade->tags ?? []),
                $this->safe($trade->entry_reason),
                $this->safe($trade->note),
            ], ',', '"', '\\');
        }
    }

    /**
     * Voorkomt dat Excel een tekstcel als formule uitvoert (CSV-injectie).
     */
    private function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
