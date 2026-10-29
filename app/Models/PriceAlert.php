<?php

namespace App\Models;

use Database\Factories\PriceAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class PriceAlert extends Model
{
    /** @use HasFactory<PriceAlertFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['triggered_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTriggered(): bool
    {
        return $this->triggered_at !== null;
    }
}
