<?php

namespace App\Enums;

enum Timeframe: string
{
    case OneMinute = '1Min';
    case FiveMinutes = '5Min';
    case FifteenMinutes = '15Min';
    case OneHour = '1Hour';
    case OneDay = '1Day';

    public function seconds(): int
    {
        return match ($this) {
            self::OneMinute => 60,
            self::FiveMinutes => 300,
            self::FifteenMinutes => 900,
            self::OneHour => 3600,
            self::OneDay => 86400,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OneMinute => '1 min',
            self::FiveMinutes => '5 min',
            self::FifteenMinutes => '15 min',
            self::OneHour => '1 uur',
            self::OneDay => '1 dag',
        };
    }
}
