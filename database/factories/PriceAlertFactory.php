<?php

namespace Database\Factories;

use App\Models\PriceAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceAlert>
 */
class PriceAlertFactory extends Factory
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
            'direction' => 'above',
            'price_cents' => 20000,
        ];
    }
}
