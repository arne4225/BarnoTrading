<?php

namespace Database\Factories;

use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
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
            'quantity' => 10,
            'cost_basis_cents' => 190000,
        ];
    }
}
