<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PortfolioService $portfolio): View
    {
        $user = $request->user();

        return view('dashboard', [
            'summary' => $portfolio->summary($user),
            'openOrders' => $user->orders()
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Held])
                ->latest('id')->get(),
        ]);
    }
}
