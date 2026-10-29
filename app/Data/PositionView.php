<?php

namespace App\Data;

use App\Models\Position;

final readonly class PositionView
{
    public function __construct(
        public Position $position,
        public int $priceCents,
        public int $marketValueCents,
        public int $unrealizedPnlCents,
        public float $unrealizedPnlPercent,
        public ?Quote $quote,
    ) {}
}
