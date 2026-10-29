<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class Candle
{
    public function __construct(
        public CarbonImmutable $time,
        public int $openCents,
        public int $highCents,
        public int $lowCents,
        public int $closeCents,
        public int $volume,
    ) {}
}
