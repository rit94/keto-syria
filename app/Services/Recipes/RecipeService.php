<?php

namespace App\Services\Recipes;

use App\Domain\Nutrition\NutritionCalculator;
use App\Domain\Nutrition\RecipeNutrition;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecipeService
{
    public function __construct(
        private readonly NutritionCalculator $calculator,
        private readonly UnitResolver $units,
    ) {}

    /**
     * Create a recipe, or replace an existing one (ingredients are replaced as a whole).
     *
     * $data: name, description, instructions, servings, cooked_weight_g, origin,
     *        ingredients[[food_id, quantity, unit]]
     *
     * Each ingredient's grams are resolved once here through UnitResolver and stored; the engine
     * only ever sees grams.
     *
     * @throws ValidationException bad data, or a unit that is not defined for an ingredient's food
     * @throws AuthorizationException recipe owned by someone else, or a food the user cannot see
     */
    public function save(User $user, array $data, ?Recipe $recipe = null): Recipe
    {
        if ($recipe !== null && (int) $recipe->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('nutrition_errors.recipe_not_accessible'));
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'servings' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'cooked_weight_g' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'origin' => ['sometimes', 'in:user,ai'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.food_id' => ['required', 'integer'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:30'],
        ])->validate();

        $ingredients = array_values($validated['ingredients']);

        $foods = Food::query()->visibleTo($user)->with('units')
            ->whereIn('id', array_column($ingredients, 'food_id'))
            ->get()->keyBy('id');

        $rows = [];
        foreach ($ingredients as $position => $ingredient) {
            $food = $foods->get((int) $ingredient['food_id'])
                ?? throw new AuthorizationException(__('nutrition_errors.food_not_accessible'));

            $unit = UnitResolver::normalize($ingredient['unit'] ?? 'g');

            try {
                $grams = $this->units->toGrams($food, (float) $ingredient['quantity'], $unit);
            } catch (UnknownUnitException $e) {
                throw ValidationException::withMessages(["ingredients.$position.unit" => $e->getMessage()]);
            } catch (InvalidQuantityException $e) {
                throw ValidationException::withMessages(["ingredients.$position.quantity" => $e->getMessage()]);
            }

            $rows[] = [
                'food_id' => $food->id,
                'quantity' => (float) $ingredient['quantity'],
                'unit' => $unit,
                'grams' => $grams,
                'position' => $position,
            ];
        }

        return DB::transaction(function () use ($user, $validated, $rows, $recipe) {
            $recipe ??= new Recipe(['user_id' => $user->id]);

            $recipe->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
                'servings' => (float) $validated['servings'],
                'cooked_weight_g' => isset($validated['cooked_weight_g']) ? (float) $validated['cooked_weight_g'] : null,
                'origin' => $validated['origin'] ?? $recipe->origin ?? 'user',
            ])->save();

            $recipe->ingredients()->delete();
            $recipe->ingredients()->createMany($rows);

            return $recipe->unsetRelation('ingredients')->load('ingredients.food');
        });
    }

    /**
     * Whole-recipe / per-serving / per-100 g nutrition, straight from the engine.
     * Pass $servings to ask "what if this were divided into N servings" without saving.
     */
    public function nutrition(Recipe $recipe, ?float $servings = null): RecipeNutrition
    {
        $recipe->loadMissing('ingredients.food');

        $ingredients = $recipe->ingredients
            ->map(fn ($ingredient) => ['per100g' => $ingredient->food->per100g(), 'grams' => $ingredient->grams])
            ->all();

        return $this->calculator->recipe($ingredients, $servings ?? $recipe->servings, $recipe->cooked_weight_g);
    }

    /** A recipe is an estimate as soon as any of its ingredient foods is. */
    public function isEstimate(Recipe $recipe): bool
    {
        $recipe->loadMissing('ingredients.food');

        return $recipe->ingredients->contains(fn ($ingredient) => (bool) $ingredient->food->is_estimate);
    }

    /** Log entries and plan items that used the recipe keep their snapshot (FK is nulled). */
    public function delete(Recipe $recipe): void
    {
        $recipe->delete();
    }
}
