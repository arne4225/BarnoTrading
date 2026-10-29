<?php

namespace App\View\Components;

use App\Data\MarketClock;
use App\Services\MarketData\MarketDataService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MarketStatus extends Component
{
    public MarketClock $clock;

    public function __construct(public MarketDataService $marketData)
    {
        $this->clock = $marketData->clock();
    }

    public function isFake(): bool
    {
        return config('trading.market_data_driver') === 'fake';
    }

    public function render(): View
    {
        return view('components.market-status');
    }
}
