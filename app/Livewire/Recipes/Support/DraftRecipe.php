<?php

namespace App\Livewire\Recipes\Support;

use App\Models\Food;
use App\Models\Recipe;
use App\Models\RecipeIngredient;

/**
 * An unsaved, in-memory Recipe (relations pre-set, nothing touches the database) so that
 * RecipeService::nutrition() / isEstimate() can be asked about a recipe that is still being
 * edited, or about a saved recipe scaled to another number of servings. The engine does the maths.
 */
class DraftRecipe
{
    /**
     * @param  iterable<array{food: Food, quantity: float, unit: string, grams: float}>  $rows
     */
    public static function make(float $servings, ?float $cookedWeightGrams, iterable $rows): Recipe
    {
        $ingredients = collect();

        foreach ($rows as $position => $row) {
            $ingredients->push(
                (new RecipeIngredient([
                    'food_id' => $row['food']->id,
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'],
                    'grams' => $row['grams'],
                    'position' => $position,
                ]))->setRelation('food', $row['food'])
            );
        }

        return (new Recipe(['servings' => $servings, 'cooked_weight_g' => $cookedWeightGrams]))
            ->setRelation('ingredients', $ingredients);
    }

    /**
     * The same dish cooked for another number of servings: every ingredient weight (and the measured
     * cooked weight) is scaled by the same factor. Only weights are scaled here.
     */
    public static function scaled(Recipe $recipe, float $servings): Recipe
    {
        $recipe->loadMissing('ingredients.food');
        $factor = $recipe->servings > 0 ? $servings / $recipe->servings : 1.0;

        return self::make(
            $servings,
            $recipe->cooked_weight_g === null ? null : $recipe->cooked_weight_g * $factor,
            $recipe->ingredients->map(fn (RecipeIngredient $ingredient) => [
                'food' => $ingredient->food,
                'quantity' => $ingredient->quantity * $factor,
                'unit' => $ingredient->unit,
                'grams' => $ingredient->grams * $factor,
            ]),
        );
    }
}
