<?php

namespace App\Domain\Nutrition;

use InvalidArgumentException;

/**
 * The deterministic nutrition engine. Pure PHP: no database, no framework, no AI.
 * Every number the application shows for food ultimately comes from here.
 */
final class NutritionCalculator
{
    /** Nutrients in $grams of a food whose facts are given per 100 g. */
    public function forGrams(Nutrients $per100g, float $grams): Nutrients
    {
        $this->assertAmount($grams, 'grams');

        return $per100g->scale($grams / 100);
    }

    /**
     * Sum of several ingredients.
     *
     * @param  iterable<array{0: Nutrients, 1: float}|array{per100g: Nutrients, grams: float}>  $ingredients
     */
    public function forIngredients(iterable $ingredients): Nutrients
    {
        $parts = [];
        foreach ($ingredients as $ingredient) {
            $per100g = $ingredient['per100g'] ?? $ingredient[0];
            $grams = $ingredient['grams'] ?? $ingredient[1];
            $parts[] = $this->forGrams($per100g, (float) $grams);
        }

        return Nutrients::sum($parts);
    }

    /**
     * Whole-recipe nutrition plus per-serving and (when the finished weight is known) per-100 g values.
     *
     * @param  iterable<array{0: Nutrients, 1: float}|array{per100g: Nutrients, grams: float}>  $ingredients
     */
    public function recipe(iterable $ingredients, float $servings, ?float $cookedWeightGrams = null): RecipeNutrition
    {
        if (! is_finite($servings) || $servings <= 0) {
            throw new InvalidArgumentException('Servings must be greater than zero.');
        }
        if ($cookedWeightGrams !== null && (! is_finite($cookedWeightGrams) || $cookedWeightGrams <= 0)) {
            throw new InvalidArgumentException('Cooked weight must be greater than zero.');
        }

        $rawWeight = 0.0;
        $list = [];
        foreach ($ingredients as $ingredient) {
            $list[] = $ingredient;
            $rawWeight += (float) ($ingredient['grams'] ?? $ingredient[1]);
        }

        $total = $this->forIngredients($list);
        $finishedWeight = $cookedWeightGrams ?? $rawWeight;

        return new RecipeNutrition(
            total: $total,
            perServing: $total->scale(1 / $servings),
            per100g: $finishedWeight > 0 ? $total->scale(100 / $finishedWeight) : null,
            servings: $servings,
            rawWeightGrams: $rawWeight,
            finishedWeightGrams: $finishedWeight,
            finishedWeightIsMeasured: $cookedWeightGrams !== null,
        );
    }

    /** Nutrients for a number of servings of something whose per-serving value is known. */
    public function forServings(Nutrients $perServing, float $servings): Nutrients
    {
        $this->assertAmount($servings, 'servings');

        return $perServing->scale($servings);
    }

    /**
     * Convert a weight measured after cooking to the equivalent raw weight, for a food whose
     * facts are stored raw. $yield = cooked weight / raw weight (e.g. 0.70 for grilled meat).
     */
    public function rawGramsFromCooked(float $cookedGrams, float $yield): float
    {
        $this->assertAmount($cookedGrams, 'grams');
        if (! is_finite($yield) || $yield <= 0) {
            throw new InvalidArgumentException('Cooked yield must be greater than zero.');
        }

        return $cookedGrams / $yield;
    }

    /**
     * Totals for a day (or any set of already-calculated entries).
     *
     * @param  iterable<Nutrients>  $entries
     */
    public function dailyTotals(iterable $entries): Nutrients
    {
        return Nutrients::sum($entries);
    }

    public function remaining(MacroTargets $targets, Nutrients $consumed): RemainingMacros
    {
        return new RemainingMacros(
            calories: $targets->calories - $consumed->calories,
            protein: $targets->protein - $consumed->protein,
            fat: $targets->fat - $consumed->fat,
            carbs: $targets->carbs - $consumed->carbs,
            netCarbs: $targets->netCarbs - $consumed->netCarbs(),
            // consumed totals are a lower bound when some entries lacked data
            isLowerBound: ! $consumed->isComplete(),
        );
    }

    private function assertAmount(float $value, string $name): void
    {
        if (! is_finite($value) || $value < 0) {
            throw new InvalidArgumentException("Amount [$name] must be a finite, non-negative number.");
        }
    }
}
