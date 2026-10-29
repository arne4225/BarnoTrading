<?php

namespace App\Http\Controllers;

use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $leaderboard): View
    {
        $period = array_key_exists((string) $request->query('period'), LeaderboardService::PERIODS)
            ? (string) $request->query('period')
            : 'all';

        return view('leaderboard.index', [
            'period' => $period,
            'periods' => LeaderboardService::PERIODS,
            'ranking' => $leaderboard->ranking($period),
        ]);
    }
}
