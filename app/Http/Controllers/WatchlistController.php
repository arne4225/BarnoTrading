<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWatchlistItemRequest;
use App\Models\WatchlistItem;
use App\Services\MarketData\MarketDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function index(Request $request, MarketDataService $marketData): View
    {
        $items = $request->user()->watchlistItems()->orderBy('symbol')->get();

        return view('watchlist.index', [
            'items' => $items,
            'quotes' => $marketData->quotes($items->pluck('symbol')->all()),
        ]);
    }

    public function store(StoreWatchlistItemRequest $request): RedirectResponse
    {
        $request->user()->watchlistItems()->firstOrCreate(['symbol' => $request->validated('symbol')]);

        return back()->with('status', $request->validated('symbol').' toegevoegd aan je watchlist.');
    }

    public function destroy(WatchlistItem $watchlistItem): RedirectResponse
    {
        Gate::authorize('delete', $watchlistItem);

        $watchlistItem->delete();

        return back()->with('status', $watchlistItem->symbol.' verwijderd uit je watchlist.');
    }
}
