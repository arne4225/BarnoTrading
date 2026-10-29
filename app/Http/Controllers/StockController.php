<?php

namespace App\Http\Controllers;

use App\Enums\Timeframe;
use App\Http\Requests\ChartDataRequest;
use App\Services\ChartDataService;
use App\Services\MarketData\MarketDataService;
use App\Services\PortfolioService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request, MarketDataService $marketData): View
    {
        $query = trim((string) $request->query('q'));
        $results = $marketData->search($query);
        $direct = MarketDataService::normalizeSymbol($query);

        return view('stocks.index', [
            'query' => $query,
            'results' => $results,
            'quotes' => $marketData->quotes(array_slice(array_keys($results ?: config('trading.symbols')), 0, 12)),
            'names' => $results ?: array_slice(config('trading.symbols'), 0, 12, true),
            'directSymbol' => MarketDataService::isValidSymbol($direct) && ! isset($results[$direct]) ? $direct : null,
        ]);
    }

    public function show(Request $request, string $symbol, MarketDataService $marketData, PortfolioService $portfolio): View
    {
        $symbol = MarketDataService::normalizeSymbol($symbol);
        abort_unless(MarketDataService::isValidSymbol($symbol), 404);

        $timeframe = Timeframe::tryFrom((string) $request->query('tf')) ?? Timeframe::FiveMinutes;
        $user = $request->user();
        $quotes = $marketData->quotes([$symbol]);

        return view('stocks.show', [
            'symbol' => $symbol,
            'name' => config("trading.symbols.{$symbol}"),
            'quote' => $quotes[$symbol] ?? null,
            'timeframe' => $timeframe,
            'position' => $portfolio->positionFor($user, $symbol),
            'buyingPowerCents' => $portfolio->buyingPowerCents($user),
            'watchlistItem' => $user->watchlistItems()->where('symbol', $symbol)->first(),
            'openOrders' => $user->orders()->where('symbol', $symbol)->whereIn('status', ['pending', 'held'])->latest('id')->get(),
        ]);
    }

    public function chart(ChartDataRequest $request, string $symbol, ChartDataService $charts): JsonResponse
    {
        $symbol = MarketDataService::normalizeSymbol($symbol);
        abort_unless(MarketDataService::isValidSymbol($symbol), 404);

        return response()->json($charts->build($symbol, $request->timeframe(), $request->indicatorConfig()));
    }

    public function quote(string $symbol, MarketDataService $marketData): JsonResponse
    {
        $symbol = MarketDataService::normalizeSymbol($symbol);
        abort_unless(MarketDataService::isValidSymbol($symbol), 404);

        $quote = $marketData->quotes([$symbol])[$symbol] ?? null;

        return response()->json([
            'price' => $quote ? Money::format($quote->priceCents) : null,
            'price_cents' => $quote?->priceCents,
            'delay' => $quote?->delayLabel(),
            'stale' => $quote?->isStale ?? true,
            'market_open' => $marketData->isMarketOpen(),
            'error' => $marketData->error(),
        ]);
    }
}
