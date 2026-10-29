<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTradeRequest;
use App\Models\Trade;
use App\Services\TradeExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('journal.index', [
            'trades' => $user->trades()->latest('opened_at')->paginate(15),
            'knownTags' => $user->trades()->whereNotNull('tags')->pluck('tags')->flatten()->unique()->sort()->values(),
        ]);
    }

    public function update(UpdateTradeRequest $request, Trade $trade): RedirectResponse
    {
        Gate::authorize('update', $trade);

        $trade->update($request->toAttributes());

        return back()->with('status', 'Journal bijgewerkt.');
    }

    public function export(Request $request, TradeExportService $export): StreamedResponse
    {
        $trades = $request->user()->trades()->orderBy('opened_at')->cursor();

        return response()->streamDownload(
            fn () => $export->write(fopen('php://output', 'w'), $trades),
            'trades-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
