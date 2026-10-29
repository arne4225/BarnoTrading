<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'starting_cash_cents', 'cash_cents',
    'commission_cents', 'slippage_bps', 'risk_per_trade_bps', 'max_position_percent',
    'min_risk_reward_hundredths', 'warn_without_stop_loss', 'daily_loss_limit_bps', 'leaderboard_visible',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'warn_without_stop_loss' => 'boolean',
            'leaderboard_visible' => 'boolean',
        ];
    }

    /**
     * Persoonlijke commissie, anders de standaard uit config.
     */
    public function effectiveCommissionCents(): int
    {
        return $this->commission_cents ?? config('trading.commission_cents');
    }

    public function effectiveSlippageBps(): int
    {
        return $this->slippage_bps ?? config('trading.slippage_bps');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    public function portfolioSnapshots(): HasMany
    {
        return $this->hasMany(PortfolioSnapshot::class);
    }
}
