<?php

namespace App\Enums;

enum OrderSide: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function opposite(): self
    {
        return $this === self::Buy ? self::Sell : self::Buy;
    }

    public function label(): string
    {
        return $this === self::Buy ? 'Kopen' : 'Verkopen';
    }

    /**
     * Positieve richting voor een positie: kopen vergroot, verkopen verkleint.
     */
    public function sign(): int
    {
        return $this === self::Buy ? 1 : -1;
    }
}
