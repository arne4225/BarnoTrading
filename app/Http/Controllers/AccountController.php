<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetAccountRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\PortfolioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->toAttributes());

        return back()->with('status', 'Instellingen opgeslagen.');
    }

    public function reset(ResetAccountRequest $request, PortfolioService $portfolio): RedirectResponse
    {
        $portfolio->resetAccount($request->user(), $request->integer('starting_cash') * 100);

        return redirect()->route('dashboard')->with('status', 'Account gereset met een nieuw startkapitaal.');
    }
}
