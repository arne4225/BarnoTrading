<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchlistItem>
 */
class WatchlistItemFactory extends Factory
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
            'symbol' => fake()->unique()->lexify('???'),
        ];
    }
}
