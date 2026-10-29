<?php

namespace Database\Factories;

use App\Models\PortfolioSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioSnapshot>
 */
class PortfolioSnapshotFactory extends Factory
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
            'date' => today(),
            'equity_cents' => 10000000,
            'cash_cents' => 10000000,
        ];
    }
}
