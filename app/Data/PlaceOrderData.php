<?php

namespace App\Data;

use App\Enums\OrderSide;
use App\Enums\OrderType;

/**
 * Invoer voor het plaatsen van een order. Prijzen zijn gehele centen.
 */
final readonly class PlaceOrderData
{
    public function __construct(
        public string $symbol,
        public OrderSide $side,
        public OrderType $type,
        public int $quantity,
        public bool $isShort = false,
        public ?int $limitPriceCents = null,
        public ?int $stopPriceCents = null,
        public ?int $trailAmountCents = null,
        public ?int $trailPercentBps = null,
        public ?int $takeProfitCents = null,
        public ?int $stopLossCents = null,
    ) {}
}
