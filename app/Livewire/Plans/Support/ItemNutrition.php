<?php

namespace App\Livewire\Plans\Support;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Models\Food;
use App\Models\Recipe;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;

/**
 * Nutrition of ONE planned item (or of an unsaved draft of the plan), always through the engine:
 * foods via UnitResolver + NutritionCalculator::forGrams, recipes via FoodLogService::previewRecipe —
 * the same two paths MealPlanService::dayNutrition takes for saved items.
 */
class ItemNutrition
{
    public function __construct(
        private readonly NutritionCalculator $calculator,
        private readonly UnitResolver $units,
        private readonly FoodLogService $log,
    ) {}

    public function for(?Food $food, ?Recipe $recipe, float $quantity, string $unit): ?Nutrients
    {
        try {
            if ($food !== null) {
                return $this->calculator->forGrams($food->per100g(), $this->units->toGrams($food, $quantity, $unit));
            }

            if ($recipe !== null) {
                return $this->log->previewRecipe($recipe, $quantity, $unit);
            }
        } catch (UnknownUnitException|InvalidQuantityException|LoggingException) {
            return null;
        }

        return null;
    }

    /** @param iterable<Nutrients|null> $parts */
    public function total(iterable $parts): Nutrients
    {
        $valid = [];
        foreach ($parts as $part) {
            if ($part !== null) {
                $valid[] = $part;
            }
        }

        return $this->calculator->dailyTotals($valid);
    }
}
