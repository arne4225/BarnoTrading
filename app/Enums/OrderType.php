<?php

namespace App\Enums;

enum OrderType: string
{
    case Market = 'market';
    case Limit = 'limit';
    case Stop = 'stop';
    case StopLimit = 'stop_limit';
    case TrailingStop = 'trailing_stop';

    public function label(): string
    {
        return match ($this) {
            self::Market => 'Market',
            self::Limit => 'Limit',
            self::Stop => 'Stop',
            self::StopLimit => 'Stop-limit',
            self::TrailingStop => 'Trailing stop',
        };
    }

    public function needsLimitPrice(): bool
    {
        return in_array($this, [self::Limit, self::StopLimit], true);
    }

    public function needsStopPrice(): bool
    {
        return in_array($this, [self::Stop, self::StopLimit], true);
    }
}
