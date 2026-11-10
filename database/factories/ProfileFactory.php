<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Profile> */
class ProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sex' => 'male',
            'birth_date' => '1990-01-01',
            'height_cm' => 175,
            'weight_kg' => 85,
            'goal_weight_kg' => 75,
            'activity_level' => 'light',
            'goal' => 'lose',
            'approach' => 'standard_keto',
            'target_calories' => 1900,
            'target_protein_g' => 130,
            'target_fat_g' => 140,
            'target_carbs_g' => 35,
            'target_net_carbs_g' => 25,
        ];
    }
}
