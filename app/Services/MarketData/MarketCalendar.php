<?php

namespace App\Services\MarketData;

use App\Data\MarketClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Lokale NYSE-kalender (09:30-16:00 Eastern Time, werkdagen, zonder feestdagen).
 * Vervroegde sluitingen (13:00) worden niet gemodelleerd.
 */
class MarketCalendar
{
    public function isOpen(?CarbonInterface $at = null): bool
    {
        if (config('trading.ignore_market_hours')) {
            return true;
        }

        $et = $this->toEastern($at);

        return $this->isTradingDay($et)
            && $et->greaterThanOrEqualTo($et->setTime(9, 30))
            && $et->lessThan($et->setTime(16, 0));
    }

    public function clock(?CarbonInterface $at = null): MarketClock
    {
        $et = $this->toEastern($at);
        $isOpen = $this->isOpen($at);

        $nextOpen = $et->setTime(9, 30);
        if ($isOpen || $et->greaterThanOrEqualTo($nextOpen)) {
            $nextOpen = $nextOpen->addDay();
        }
        while (! $this->isTradingDay($nextOpen)) {
            $nextOpen = $nextOpen->addDay();
        }

        $nextClose = $et->setTime(16, 0);
        if ($et->greaterThanOrEqualTo($nextClose) || (! $isOpen && ! config('trading.ignore_market_hours'))) {
            $nextClose = $nextOpen->setTime(16, 0);
        }

        return new MarketClock($isOpen, $nextOpen, $nextClose);
    }

    private function isTradingDay(CarbonImmutable $et): bool
    {
        return ! $et->isWeekend()
            && ! in_array($et->toDateString(), config('trading.holidays', []), true);
    }

    private function toEastern(?CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance(($at ?? now())->toDateTimeImmutable())
            ->setTimezone(config('trading.timezone'));
    }
}
