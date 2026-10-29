<?php

namespace App\Models;

use Database\Factories\PortfolioSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class PortfolioSnapshot extends Model
{
    /** @use HasFactory<PortfolioSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
