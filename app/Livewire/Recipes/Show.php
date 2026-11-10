<?php

namespace App\Livewire\Recipes;

use App\Domain\Nutrition\NutritionCalculator;
use App\Enums\Meal;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Recipes\Support\Amount;
use App\Livewire\Recipes\Support\DraftRecipe;
use App\Models\Recipe;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;
use App\Services\Recipes\RecipeService;
use App\View\Format;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests, Toasts;

    public const LOG_SHEET = 'log-recipe';

    public const DELETE_MODAL = 'delete-recipe';

    #[Locked]
    public int $recipeId;

    /** Serving adjuster: how many servings to cook / show the ingredients for. */
    public $servings = 1;

    public string $logMeal = 'lunch';

    public string $logDate = '';

    public $logQuantity = 1;

    public string $logUnit = 'serving';

    public function mount($recipe): void
    {
        $recipe = $recipe instanceof Recipe ? $recipe : Recipe::findOrFail($recipe);
        $this->authorize('view', $recipe);

        $this->recipeId = $recipe->id;
        $this->servings = $recipe->servings;
        $this->logDate = today()->toDateString();
        $this->logMeal = self::mealForHour((int) now()->format('G'))->value;
    }

    public function resetServings(): void
    {
        $this->servings = $this->recipe('view')->servings;
    }

    public function log(FoodLogService $log): void
    {
        $recipe = $this->recipe('view');

        $this->logQuantity = is_string($this->logQuantity)
            ? str_replace(',', '.', trim(Format::westernDigits($this->logQuantity)))
            : $this->logQuantity;

        $this->validate([
            'logQuantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'logUnit' => ['required', Rule::in(['serving', 'g'])],
            'logMeal' => ['required', Rule::enum(Meal::class)],
            'logDate' => ['required', 'date'],
        ], [], [
            'logQuantity' => __('recipes.log.quantity'),
            'logUnit' => __('recipes.log.unit'),
            'logMeal' => __('recipes.log.meal'),
            'logDate' => __('recipes.log.date'),
        ]);

        try {
            $log->logRecipe(
                auth()->user(), $recipe, (float) $this->logQuantity, $this->logUnit,
                Meal::from($this->logMeal), Carbon::parse($this->logDate),
            );
        } catch (LoggingException $e) {
            // e.g. recipe_has_no_weight: keep the sheet open and say what to do instead
            $this->addError('logUnit', $e->getMessage());

            return;
        } catch (UnknownUnitException $e) {
            $this->addError('logUnit', $e->getMessage());

            return;
        } catch (InvalidQuantityException $e) {
            $this->addError('logQuantity', $e->getMessage());

            return;
        }

        $this->dispatch('close-modal', name: self::LOG_SHEET);
        $this->toast(__('recipes.log.logged', ['name' => $recipe->name]));
        $this->logQuantity = 1;
        $this->logUnit = 'serving';
    }

    public function duplicate(RecipeService $service)
    {
        $recipe = $this->recipe('view');

        $copy = $service->save(auth()->user(), [
            'name' => mb_substr(__('recipes.copy_of', ['name' => $recipe->name]), 0, 255),
            'description' => $recipe->description,
            'instructions' => $recipe->instructions,
            'servings' => $recipe->servings,
            'cooked_weight_g' => $recipe->cooked_weight_g,
            'origin' => 'user',
            'ingredients' => $recipe->ingredients->map(fn ($ingredient) => [
                'food_id' => $ingredient->food_id,
                'quantity' => $ingredient->quantity,
                'unit' => $ingredient->unit,
            ])->all(),
        ]);

        $this->flashToast(__('recipes.duplicated'));

        return $this->redirect(route('recipes.edit', $copy), navigate: true);
    }

    public function delete(RecipeService $service)
    {
        $service->delete($this->recipe('delete'));

        $this->flashToast(__('recipes.deleted'));

        return $this->redirect(route('recipes.index'), navigate: true);
    }

    public function render(RecipeService $service, FoodLogService $log, NutritionCalculator $calculator)
    {
        $recipe = $this->recipe('view');

        $base = $service->nutrition($recipe);

        // The adjuster: the same dish cooked for N servings. Ingredient weights are scaled, the
        // engine recalculates; per-serving figures stay what they were.
        $servings = Amount::parse($this->servings) ?? $recipe->servings;
        $scaled = DraftRecipe::scaled($recipe, $servings);
        $nutrition = $service->nutrition($scaled, $servings);

        $ingredients = $scaled->ingredients->map(fn ($ingredient) => [
            'food' => $ingredient->food,
            'quantity' => $ingredient->quantity,
            'unit' => Amount::unitLabel($ingredient->food, $ingredient->unit),
            'unit_key' => $ingredient->unit,
            'grams' => $ingredient->grams,
            'contribution' => $calculator->forGrams($ingredient->food->per100g(), $ingredient->grams),
        ]);

        // Log preview — exactly what FoodLogService::logRecipe would store.
        $preview = null;
        $previewError = null;
        $quantity = Amount::parse($this->logQuantity);

        if ($quantity !== null) {
            try {
                $preview = $log->previewRecipe($recipe, $quantity, $this->logUnit);
            } catch (LoggingException $e) {
                $previewError = $e->getMessage();
            } catch (UnknownUnitException|InvalidQuantityException) {
                // nothing to preview
            }
        }

        return view('livewire.recipes.show', [
            'recipe' => $recipe,
            'base' => $base,
            'nutrition' => $nutrition,
            'servingsShown' => $servings,
            'isScaled' => abs($servings - $recipe->servings) > 0.0001,
            'ingredients' => $ingredients,
            'estimate' => $service->isEstimate($recipe),
            'preview' => $preview,
            'previewError' => $previewError,
            'meals' => collect(Meal::cases())->mapWithKeys(fn (Meal $meal) => [$meal->value => $meal->label()])->all(),
        ])
            ->title($recipe->name)
            ->layoutData(['back' => route('recipes.index')]);
    }

    /** Reloaded and re-authorised on every request: the id is locked, the policy decides. */
    private function recipe(string $ability): Recipe
    {
        $recipe = Recipe::with('ingredients.food.units')->findOrFail($this->recipeId);
        $this->authorize($ability, $recipe);

        return $recipe;
    }

    private static function mealForHour(int $hour): Meal
    {
        return match (true) {
            $hour < 11 => Meal::Breakfast,
            $hour < 16 => Meal::Lunch,
            $hour < 22 => Meal::Dinner,
            default => Meal::Snack,
        };
    }
}
