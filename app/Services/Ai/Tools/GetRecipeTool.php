<?php

namespace App\Services\Ai\Tools;

use App\Domain\Nutrition\NutritionCalculator;
use App\Models\CoachConversation;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Ai\Support\FoodPresenter;
use App\Services\Recipes\RecipeService;

class GetRecipeTool extends AbstractTool
{
    public function __construct(
        private readonly RecipeService $recipes,
        private readonly NutritionCalculator $calculator,
        private readonly FoodPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'get_recipe';
    }

    public function description(): string
    {
        return 'One of the user\'s recipes with ingredients, instructions and nutrition (whole recipe, per serving, per 100 g when a weight is known). '
            .'Pass servings to see the per-serving values if the recipe were divided differently; this changes nothing in the saved recipe.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'recipe_id' => ['type' => 'integer'],
            'servings' => ['type' => 'number', 'description' => 'Optional what-if number of servings.'],
        ], ['recipe_id']);
    }

    protected function rules(): array
    {
        return [
            'recipe_id' => ['required', 'integer'],
            'servings' => ['sometimes', 'numeric', 'gt:0', 'max:1000'],
        ];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $recipe = Recipe::query()->where('user_id', $user->id)->with('ingredients.food')->find((int) $data['recipe_id']);

        if ($recipe === null) {
            return self::error('recipe_not_found', 'This user has no recipe with this id.');
        }

        $result = [
            'recipe_id' => $recipe->id,
            'name' => $recipe->name,
            'description' => $recipe->description,
            'instructions' => $recipe->instructions,
            'saved_servings' => $recipe->servings,
            'cooked_weight_g' => $recipe->cooked_weight_g,
            'ingredients' => $recipe->ingredients->map(fn ($ingredient) => [
                'food_id' => $ingredient->food_id,
                'name_ar' => $ingredient->food?->name_ar,
                'name_en' => $ingredient->food?->name_en,
                'quantity' => $ingredient->quantity,
                'unit' => $ingredient->unit,
                'grams' => $ingredient->grams,
                'nutrition' => $ingredient->food === null
                    ? null
                    : $this->presenter->nutrients($this->calculator->forGrams($ingredient->food->per100g(), $ingredient->grams)),
            ])->all(),
        ];

        if ($recipe->ingredients->isEmpty()) {
            return $result + ['nutrition' => null];
        }

        $nutrition = $this->recipes->nutrition($recipe, isset($data['servings']) ? (float) $data['servings'] : null);

        return $result + [
            'nutrition' => [
                'servings' => $nutrition->servings,
                'total' => $this->presenter->nutrients($nutrition->total),
                'per_serving' => $this->presenter->nutrients($nutrition->perServing),
                'per_100g' => $nutrition->per100g === null ? null : $this->presenter->nutrients($nutrition->per100g),
                'finished_weight_g' => $nutrition->finishedWeightGrams,
                'finished_weight_is_measured' => $nutrition->finishedWeightIsMeasured,
            ],
            'is_estimate' => $this->recipes->isEstimate($recipe),
        ];
    }
}
