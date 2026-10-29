<?php

namespace App\Models;

use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isShort(): bool
    {
        return $this->quantity < 0;
    }

    /**
     * Gemiddelde instapprijs in centen (afgerond, alleen voor weergave).
     */
    public function averagePriceCents(): int
    {
        return $this->quantity === 0 ? 0 : (int) round($this->cost_basis_cents / abs($this->quantity));
    }
}
