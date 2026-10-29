<?php

namespace Database\Factories;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 */
class TradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => 'AAPL',
            'direction' => 'long',
            'peak_quantity' => 10,
            'open_quantity' => 10,
            'open_value_cents' => 100_000,
            'close_quantity' => 10,
            'close_value_cents' => 110_000,
            'realized_pnl_cents' => 10_000,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['close_quantity' => 0, 'close_value_cents' => 0, 'realized_pnl_cents' => 0, 'closed_at' => null]);
    }

    public function withPnl(int $pnlCents): static
    {
        return $this->state(fn () => ['realized_pnl_cents' => $pnlCents, 'close_value_cents' => 100_000 + $pnlCents]);
    }
}
