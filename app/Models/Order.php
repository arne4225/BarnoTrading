<?php

namespace App\Models;

use App\Enums\BracketRole;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'side' => OrderSide::class,
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'bracket_role' => BracketRole::class,
            'is_short' => 'boolean',
            'stop_triggered_at' => 'datetime',
            'filled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
