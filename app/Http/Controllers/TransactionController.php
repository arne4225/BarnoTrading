<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('transactions.index', [
            'transactions' => $user->transactions()->latest('executed_at')->latest('id')->paginate(25),
            'realizedPnlCents' => (int) $user->transactions()->sum('realized_pnl_cents'),
            'commissionCents' => (int) $user->transactions()->sum('commission_cents'),
        ]);
    }
}
