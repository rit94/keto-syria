<?php

namespace App\Models;

use App\Domain\Nutrition\Nutrients;
use App\Enums\LogSource;
use App\Enums\Meal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodLogEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'meal' => Meal::class,
            'source' => LogSource::class,
            'quantity' => 'float',
            'grams' => 'float',
            'weighed_cooked' => 'boolean',
            'calories' => 'float',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fiber_g' => 'float',
            'fat_g' => 'float',
            'saturated_fat_g' => 'float',
            'missing' => 'array',
            'is_estimate' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** The stored snapshot as an engine value object (what daily totals are summed from). */
    public function nutrients(): Nutrients
    {
        return new Nutrients(
            calories: $this->calories,
            protein: $this->protein_g,
            carbs: $this->carbs_g,
            fiber: $this->fiber_g,
            fat: $this->fat_g,
            saturatedFat: $this->saturated_fat_g,
            missing: $this->missing ?? [],
        );
    }

    /**
     * Column values for a snapshot of engine output.
     *
     * @return array<string, mixed>
     */
    public static function snapshotColumns(Nutrients $n): array
    {
        return [
            'calories' => $n->calories,
            'protein_g' => $n->protein,
            'carbs_g' => $n->carbs,
            'fiber_g' => $n->fiber,
            'fat_g' => $n->fat,
            'saturated_fat_g' => $n->saturatedFat,
            'missing' => $n->missing,
        ];
    }
}
