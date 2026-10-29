<?php

namespace App\Models;

use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een round-trip: van het openen van een positie tot ze weer op nul staat.
 */
#[Guarded([])]
class Trade extends Model
{
    /** @use HasFactory<TradeFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isShort(): bool
    {
        return $this->direction === 'short';
    }

    public function averageEntryCents(): int
    {
        return $this->open_quantity === 0 ? 0 : (int) round($this->open_value_cents / $this->open_quantity);
    }

    public function averageExitCents(): ?int
    {
        return $this->close_quantity === 0 ? null : (int) round($this->close_value_cents / $this->close_quantity);
    }
}
