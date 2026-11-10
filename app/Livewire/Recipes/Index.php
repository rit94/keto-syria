<?php

namespace App\Livewire\Recipes;

use App\Models\Recipe;
use App\Services\Food\ArabicNormalizer;
use App\Services\Recipes\RecipeService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class Index extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    public function render(RecipeService $service)
    {
        $recipes = Recipe::query()
            ->where('user_id', auth()->id())
            ->with('ingredients.food')
            ->latest('updated_at')
            ->latest('id')
            ->get();

        $total = $recipes->count();
        $needle = ArabicNormalizer::normalize(trim($this->query));

        if ($needle !== '') {
            $recipes = $recipes->filter(fn (Recipe $recipe) => str_contains(
                ArabicNormalizer::normalize($recipe->name.' '.$recipe->description), $needle,
            ))->values();
        }

        $cards = $recipes->map(function (Recipe $recipe) use ($service) {
            try {
                $nutrition = $service->nutrition($recipe);
            } catch (Throwable) {
                $nutrition = null;   // e.g. a legacy row with zero servings: show the card without figures
            }

            return [
                'recipe' => $recipe,
                'nutrition' => $nutrition,
                'estimate' => $service->isEstimate($recipe),
            ];
        });

        return view('livewire.recipes.index', ['cards' => $cards, 'total' => $total])
            ->title(__('recipes.title'));
    }
}
