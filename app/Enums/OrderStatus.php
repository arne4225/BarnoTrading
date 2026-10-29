<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Held = 'held';
    case Filled = 'filled';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Open',
            self::Held => 'Wacht op entry',
            self::Filled => 'Uitgevoerd',
            self::Cancelled => 'Geannuleerd',
            self::Rejected => 'Afgewezen',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Held], true);
    }
}
