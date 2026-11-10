<?php

namespace App\Models;

use App\Domain\Nutrition\MacroTargets;
use App\Enums\ActivityLevel;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'activity_level' => ActivityLevel::class,
            'goal' => WeightGoal::class,
            'approach' => DietApproach::class,
            'birth_date' => 'date',
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'goal_weight_kg' => 'float',
            'needs_medical_supervision' => 'boolean',
            'target_calories' => 'float',
            'target_protein_g' => 'float',
            'target_fat_g' => 'float',
            'target_carbs_g' => 'float',
            'target_net_carbs_g' => 'float',
            'targets_customized' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function age(): int
    {
        return (int) $this->birth_date->diffInYears(now());
    }

    public function targets(): MacroTargets
    {
        return new MacroTargets(
            calories: $this->target_calories,
            protein: $this->target_protein_g,
            fat: $this->target_fat_g,
            carbs: $this->target_carbs_g,
            netCarbs: $this->target_net_carbs_g,
        );
    }
}
