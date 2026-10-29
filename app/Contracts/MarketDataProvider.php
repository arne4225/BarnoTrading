<?php

namespace App\Contracts;

use App\Data\Candle;
use App\Data\MarketClock;
use App\Data\Quote;
use App\Enums\Timeframe;
use App\Exceptions\MarketDataUnavailableException;

interface MarketDataProvider
{
    /**
     * @throws MarketDataUnavailableException
     */
    public function quote(string $symbol): Quote;

    /**
     * @param  list<string>  $symbols
     * @return array<string, Quote> Op symbool; onbekende symbolen ontbreken.
     *
     * @throws MarketDataUnavailableException
     */
    public function quotes(array $symbols): array;

    /**
     * @return list<Candle> Oudste eerst.
     *
     * @throws MarketDataUnavailableException
     */
    public function candles(string $symbol, Timeframe $timeframe, int $limit = 100): array;

    public function marketClock(): MarketClock;

    public function isMarketOpen(): bool;
}
