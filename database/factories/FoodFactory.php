<?php

namespace Database\Factories;

use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Food> */
class FoodFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name_ar' => $name,
            'name_en' => $name,
            'category' => 'other',
            'state' => 'raw',
            'calories' => 100,
            'protein_g' => 10,
            'carbs_g' => 5,
            'fiber_g' => 2,
            'fat_g' => 4,
            'saturated_fat_g' => 1,
            'label_basis' => 'total',
            'source' => 'test',
            'confidence' => 'high',
            'is_estimate' => false,
            'search_text' => $name,
        ];
    }
}
