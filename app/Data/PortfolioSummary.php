<?php

namespace App\Data;

final readonly class PortfolioSummary
{
    /**
     * @param  list<PositionView>  $positions
     */
    public function __construct(
        public int $cashCents,
        public int $marketValueCents,
        public int $equityCents,
        public int $unrealizedPnlCents,
        public int $dailyPnlCents,
        public int $totalPnlCents,
        public int $buyingPowerCents,
        public array $positions,
    ) {}
}
