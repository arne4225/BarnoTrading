<?php

namespace Database\Factories;

use App\Enums\OrderSide;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => 'AAPL',
            'side' => OrderSide::Buy,
            'quantity' => 10,
            'price_cents' => 19000,
            'executed_at' => now(),
        ];
    }
}
