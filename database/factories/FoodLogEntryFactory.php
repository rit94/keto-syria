<?php

namespace Database\Factories;

use App\Models\FoodLogEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A free-standing snapshot row (no food / recipe attached). Real entries are written by
 * FoodLogService; use this only where a test needs a row with known numbers.
 *
 * @extends Factory<FoodLogEntry>
 */
class FoodLogEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'logged_on' => now()->toDateString(),
            'meal' => 'lunch',
            'description' => fake()->words(2, true),
            'quantity' => 100,
            'unit' => 'g',
            'grams' => 100,
            'calories' => 100,
            'protein_g' => 10,
            'carbs_g' => 5,
            'fiber_g' => 2,
            'fat_g' => 4,
            'saturated_fat_g' => 1,
            'missing' => [],
            'is_estimate' => false,
            'source' => 'manual',
        ];
    }
}
