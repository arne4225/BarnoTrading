<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWatchlistItemRequest;
use App\Http\Requests\StoreWatchlistRequest;
use App\Models\Watchlist;
use App\Models\WatchlistItem;
use App\Services\WatchlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function index(Request $request, WatchlistService $watchlists): View
    {
        $user = $request->user();
        $default = $watchlists->defaultFor($user);
        $lists = $user->watchlists()->orderBy('id')->get();
        $current = $lists->firstWhere('id', $request->integer('list')) ?? $default;

        $sort = in_array($request->query('sort'), WatchlistService::SORTS, true) ? $request->query('sort') : 'symbol';
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return view('watchlist.index', [
            'lists' => $lists,
            'current' => $current,
            'rows' => $watchlists->rows($current, $sort, $direction),
            'sort' => $sort,
            'direction' => $direction,
            'alerts' => $user->priceAlerts()->whereNull('triggered_at')->orderBy('symbol')->get(),
        ]);
    }

    public function store(StoreWatchlistItemRequest $request, WatchlistService $watchlists): RedirectResponse
    {
        $user = $request->user();
        $list = $request->filled('watchlist_id')
            ? $user->watchlists()->findOrFail($request->integer('watchlist_id'))
            : $watchlists->defaultFor($user);

        $list->items()->firstOrCreate(['symbol' => $request->validated('symbol')], ['user_id' => $user->id]);

        return back()->with('status', $request->validated('symbol')." toegevoegd aan '{$list->name}'.");
    }

    public function destroy(WatchlistItem $watchlistItem): RedirectResponse
    {
        Gate::authorize('delete', $watchlistItem);

        $watchlistItem->delete();

        return back()->with('status', $watchlistItem->symbol.' verwijderd uit je watchlist.');
    }

    public function storeList(StoreWatchlistRequest $request): RedirectResponse
    {
        $list = $request->user()->watchlists()->create($request->validated());

        return redirect()->route('watchlist.index', ['list' => $list->id])->with('status', "Lijst '{$list->name}' aangemaakt.");
    }

    public function destroyList(Request $request, Watchlist $watchlist): RedirectResponse
    {
        Gate::authorize('delete', $watchlist);

        if ($request->user()->watchlists()->count() <= 1) {
            return back()->with('error', 'Je kunt je laatste lijst niet verwijderen.');
        }

        $watchlist->delete();

        return redirect()->route('watchlist.index')->with('status', "Lijst '{$watchlist->name}' verwijderd.");
    }
}
