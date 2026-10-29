<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class MarketClock
{
    public function __construct(
        public bool $isOpen,
        public CarbonImmutable $nextOpen,
        public CarbonImmutable $nextClose,
    ) {}
}
