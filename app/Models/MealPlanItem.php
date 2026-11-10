<?php

namespace App\Models;

use App\Enums\Meal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planned_on' => 'date', 'meal' => Meal::class, 'quantity' => 'float', 'grams' => 'float'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class, 'meal_plan_id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function logEntry(): BelongsTo
    {
        return $this->belongsTo(FoodLogEntry::class, 'food_log_entry_id');
    }
}
