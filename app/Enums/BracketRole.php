<?php

namespace App\Enums;

enum BracketRole: string
{
    case TakeProfit = 'take_profit';
    case StopLoss = 'stop_loss';

    public function label(): string
    {
        return $this === self::TakeProfit ? 'Take-profit' : 'Stop-loss';
    }
}
