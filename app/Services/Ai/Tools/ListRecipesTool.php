<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Ai\Support\FoodPresenter;
use App\Services\Food\ArabicNormalizer;
use App\Services\Recipes\RecipeService;

class ListRecipesTool extends AbstractTool
{
    public function __construct(
        private readonly RecipeService $recipes,
        private readonly FoodPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'list_recipes';
    }

    public function description(): string
    {
        return 'The user\'s saved recipes (id, name, servings, nutrition per serving), optionally filtered by name. '
            .'Call it when the user mentions one of their recipes or asks what they could cook from what they saved.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'query' => ['type' => 'string', 'description' => 'Optional part of the recipe name.'],
        ]);
    }

    protected function rules(): array
    {
        return ['query' => ['sometimes', 'nullable', 'string', 'max:120']];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $recipes = Recipe::query()->where('user_id', $user->id)->with('ingredients.food')->orderByDesc('updated_at')->limit(200)->get();

        $needle = ArabicNormalizer::normalize((string) ($data['query'] ?? ''));
        if ($needle !== '') {
            $recipes = $recipes->filter(fn (Recipe $recipe) => str_contains(ArabicNormalizer::normalize($recipe->name), $needle))->values();
        }

        $recipes = $recipes->take(30);

        return [
            'count' => $recipes->count(),
            'recipes' => $recipes->map(function (Recipe $recipe) {
                $row = ['recipe_id' => $recipe->id, 'name' => $recipe->name, 'servings' => $recipe->servings];

                if ($recipe->ingredients->isEmpty()) {
                    return $row + ['per_serving' => null];
                }

                return $row + [
                    'per_serving' => $this->presenter->nutrients($this->recipes->nutrition($recipe)->perServing),
                    'is_estimate' => $this->recipes->isEstimate($recipe),
                ];
            })->all(),
        ];
    }
}
