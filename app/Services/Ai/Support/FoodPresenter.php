<?php

namespace App\Services\Ai\Support;

use App\Domain\Nutrition\Nutrients;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Services\Food\UnitResolver;

/**
 * Compact, model-facing JSON shapes. Every number is a database fact or engine output at
 * full precision; provenance flags travel with it so the model can be honest about estimates.
 */
class FoodPresenter
{
    public function __construct(private readonly UnitResolver $units) {}

    /** @return array<string, mixed> */
    public function food(Food $food, ?float $score = null): array
    {
        $food->loadMissing('units');

        $data = [
            'food_id' => $food->id,
            'name_ar' => $food->name_ar,
            'name_en' => $food->name_en,
            'category' => $food->category?->value,
            'state' => $food->state?->value,
            'preparation' => $food->preparation?->value,
            'per_100g' => $this->nutrients($food->per100g()),
            'cooked_yield' => $food->cooked_yield,
            'units' => array_map(
                fn (array $unit) => ['unit' => $unit['unit'], 'grams' => $unit['grams'], 'is_default' => $unit['is_default']],
                $this->units->unitsFor($food),
            ),
            'source' => $food->source,
            'confidence' => $food->confidence?->value,
            'is_estimate' => (bool) $food->is_estimate,
            'is_custom' => $food->isCustom(),
            'is_keto_friendly' => (bool) $food->is_keto_friendly,
            'parent_food_id' => $food->parent_food_id,
        ];

        if ($score !== null) {
            $data['score'] = $score;
        }

        return $data;
    }

    /** Engine output with an explicit list of unknown nutrients (never silently zero). */
    public function nutrients(Nutrients $nutrients): array
    {
        return $nutrients->toArray();
    }

    /** @return array<string, mixed> */
    public function entry(FoodLogEntry $entry): array
    {
        return [
            'entry_id' => $entry->id,
            'date' => $entry->logged_on->toDateString(),
            'meal' => $entry->meal->value,
            'description' => $entry->description,
            'food_id' => $entry->food_id,
            'recipe_id' => $entry->recipe_id,
            'quantity' => $entry->quantity,
            'unit' => $entry->unit,
            'grams' => $entry->grams,
            'weighed_cooked' => (bool) $entry->weighed_cooked,
            'nutrition' => $this->nutrients($entry->nutrients()),
            'is_estimate' => (bool) $entry->is_estimate,
            'source' => $entry->source?->value,
        ];
    }
}
