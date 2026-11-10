<?php

namespace App\Services\Plans;

use App\Enums\FoodCategory;
use App\Models\Food;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\Recipe;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use Illuminate\Support\Collection;

/**
 * Aggregates what to buy for a meal plan: total grams per food, recipes expanded into their
 * ingredients (scaled by the planned servings, or by grams of finished dish), grouped by category.
 *
 * Weights only — no nutrition arithmetic. Units go through UnitResolver (the plan stores grams for
 * food items already; they are re-resolved only when missing).
 */
class ShoppingListBuilder
{
    public function __construct(private readonly UnitResolver $units) {}

    /**
     * @return Collection<int, array{category: string, items: list<array{food: Food, grams: float}>}>
     *                                                                                                groups in a stable category order, items sorted by name within a group
     */
    public function build(MealPlan $plan): Collection
    {
        $plan->loadMissing('items.food.units', 'items.recipe.ingredients.food');

        /** @var array<int, array{food: Food, grams: float}> $totals */
        $totals = [];

        foreach ($plan->items as $item) {
            foreach ($this->expand($item) as $foodId => $line) {
                $totals[$foodId] ??= ['food' => $line['food'], 'grams' => 0.0];
                $totals[$foodId]['grams'] += $line['grams'];
            }
        }

        return $this->group($totals);
    }

    /** @return array<int, array{food: Food, grams: float}> */
    private function expand(MealPlanItem $item): array
    {
        if ($item->food !== null) {
            $grams = $item->grams;

            if ($grams === null) {
                try {
                    $grams = $this->units->toGrams($item->food, $item->quantity, $item->unit);
                } catch (UnknownUnitException) {
                    return [];
                }
            }

            return [$item->food->id => ['food' => $item->food, 'grams' => $grams]];
        }

        if ($item->recipe === null) {
            return [];   // deleted food / recipe
        }

        $factor = $this->recipeFactor($item->recipe, $item);
        if ($factor === null) {
            return [];
        }

        $lines = [];
        foreach ($item->recipe->ingredients as $ingredient) {
            if ($ingredient->food === null) {
                continue;
            }
            $lines[$ingredient->food_id] ??= ['food' => $ingredient->food, 'grams' => 0.0];
            $lines[$ingredient->food_id]['grams'] += $ingredient->grams * $factor;
        }

        return $lines;
    }

    /** How many "whole recipes" the item stands for. */
    private function recipeFactor(Recipe $recipe, MealPlanItem $item): ?float
    {
        if ($item->unit === 'g') {
            $finished = $recipe->cooked_weight_g ?: $recipe->ingredients->sum('grams');

            return $finished > 0 ? $item->quantity / $finished : null;
        }

        return $recipe->servings > 0 ? $item->quantity / $recipe->servings : null;
    }

    /**
     * @param  array<int, array{food: Food, grams: float}>  $totals
     * @return Collection<int, array{category: string, items: list<array{food: Food, grams: float}>}>
     */
    private function group(array $totals): Collection
    {
        $order = FoodCategory::values();

        return collect($totals)
            ->groupBy(fn (array $line) => $line['food']->category?->value ?? 'other')
            ->map(fn (Collection $lines, string $category) => [
                'category' => $category,
                'items' => $lines
                    ->sortBy(fn (array $line) => mb_strtolower($line['food']->displayName()))
                    ->values()->all(),
            ])
            ->sortBy(fn (array $group) => array_search($group['category'], $order, true) === false ? 99 : array_search($group['category'], $order, true))
            ->values();
    }
}
