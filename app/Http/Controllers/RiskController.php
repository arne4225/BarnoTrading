<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidOrderException;
use App\Exceptions\MarketDataUnavailableException;
use App\Http\Requests\RiskPreviewRequest;
use App\Services\MarketData\MarketDataService;
use App\Services\PortfolioService;
use App\Services\PositionSizingService;
use App\Services\RiskService;
use Illuminate\Http\JsonResponse;

class RiskController extends Controller
{
    /**
     * Rekent het aantal aandelen uit (bij stop-loss) en toetst een order aan de risicoregels.
     */
    public function preview(
        RiskPreviewRequest $request,
        MarketDataService $marketData,
        PortfolioService $portfolio,
        PositionSizingService $sizing,
        RiskService $risk,
    ): JsonResponse {
        $user = $request->user();

        try {
            $entry = $request->entryCents() ?? $marketData->quote($request->string('symbol')->value())->priceCents;
        } catch (MarketDataUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $summary = $portfolio->summary($user);
        $result = ['entry' => $entry / 100, 'equity' => $summary->equityCents / 100, 'sizing' => null, 'warnings' => [], 'error' => null];

        try {
            if ($request->stopLossCents() !== null) {
                $result['sizing'] = $sizing->calculate(
                    $summary->equityCents,
                    $request->riskBps() ?? $user->risk_per_trade_bps,
                    $entry,
                    $request->stopLossCents(),
                    $request->takeProfitCents(),
                    $summary->buyingPowerCents,
                )->toArray();
            }
        } catch (InvalidOrderException $e) {
            $result['error'] = $e->getMessage();
        }

        if ($request->filled('quantity')) {
            $result['warnings'] = $risk->assess($user, $request->toOrderData($entry));
        }

        return response()->json($result);
    }
}
