<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidAlertException;
use App\Http\Requests\StorePriceAlertRequest;
use App\Models\PriceAlert;
use App\Services\AlertService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PriceAlertController extends Controller
{
    public function store(StorePriceAlertRequest $request, AlertService $alerts): RedirectResponse
    {
        try {
            $alert = $alerts->create($request->user(), $request->validated('symbol'), $request->priceCents());
        } catch (InvalidAlertException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', "Alert gezet: {$alert->symbol} ".($alert->direction === 'above' ? 'boven' : 'onder').' '.Money::format($alert->price_cents).'.');
    }

    public function destroy(PriceAlert $priceAlert): RedirectResponse
    {
        Gate::authorize('delete', $priceAlert);

        $priceAlert->delete();

        return back()->with('status', 'Alert verwijderd.');
    }

    public function acknowledge(PriceAlert $priceAlert): RedirectResponse
    {
        Gate::authorize('update', $priceAlert);

        $priceAlert->update(['acknowledged_at' => now()]);

        return back();
    }
}
