<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    public function index(Request $request, StatisticsService $statistics): View
    {
        $period = (string) $request->query('period', 'all');
        $period = array_key_exists($period, StatisticsService::PERIODS) ? $period : 'all';

        return view('statistics.index', [
            'period' => $period,
            'periods' => StatisticsService::PERIODS,
            'stats' => $statistics->forUser($request->user(), $period),
        ]);
    }
}
