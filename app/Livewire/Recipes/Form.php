<?php

namespace App\Livewire\Recipes;

use App\Domain\Nutrition\NutritionCalculator;
use App\Enums\FoodState;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Foods\Picker;
use App\Livewire\Recipes\Support\Amount;
use App\Livewire\Recipes\Support\DraftRecipe;
use App\Models\Food;
use App\Models\Recipe;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Recipes\RecipeService;
use App\View\Format;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Create / edit a recipe. Ingredients arrive from the shared food picker through the
 * `food-picked` event (context "recipe"). The nutrition panel is recomputed by RecipeService
 * on every render from an in-memory draft — this component never does nutrition arithmetic.
 */
class Form extends Component
{
    use AuthorizesRequests, Toasts;

    public const PICKER_CONTEXT = 'recipe';

    public const PICKER_SHEET = 'ingredient-picker';

    #[Locked]
    public ?int $recipeId = null;

    public string $name = '';

    public string $description = '';

    public string $instructions = '';

    /** @var int|float|string|null */
    public $servings = 1;

    /** @var int|float|string|null */
    public $cookedWeight = null;

    /** @var list<array{key: string, food_id: int, quantity: mixed, unit: string}> */
    public array $ingredients = [];

    public function mount($recipe = null): void
    {
        if ($recipe === null) {
            return;
        }

        $recipe = $recipe instanceof Recipe ? $recipe : Recipe::findOrFail($recipe);
        $this->authorize('update', $recipe);

        $this->recipeId = $recipe->id;
        $this->name = $recipe->name;
        $this->description = (string) $recipe->description;
        $this->instructions = (string) $recipe->instructions;
        $this->servings = $recipe->servings;
        $this->cookedWeight = $recipe->cooked_weight_g;
        $this->ingredients = $recipe->ingredients
            ->map(fn ($ingredient) => [
                'key' => (string) Str::uuid(),
                'food_id' => (int) $ingredient->food_id,
                'quantity' => $ingredient->quantity,
                'unit' => $ingredient->unit,
            ])
            ->values()->all();
    }

    #[On('food-picked')]
    public function addIngredient(
        string $context = '',
        ?int $foodId = null,
        mixed $quantity = null,
        ?string $unit = null,
        bool $weighedCooked = false,
    ): void {
        if ($context !== self::PICKER_CONTEXT || $foodId === null) {
            return;
        }

        $food = Food::query()->visibleTo(auth()->user())->with('units')->find($foodId);
        if ($food === null) {
            return;
        }

        $amount = Amount::parse($quantity);
        $unit = $unit === null || trim($unit) === '' ? null : UnitResolver::normalize($unit);

        if ($amount === null || $unit === null) {
            ['quantity' => $amount, 'unit' => $unit] = Amount::defaultFor($food);
        }

        // Recipe ingredients are stored as the weight the food's facts describe. A raw food that
        // was weighed after cooking is converted to its raw-equivalent grams by the engine.
        if ($weighedCooked && $food->state === FoodState::Raw && $food->cooked_yield !== null) {
            try {
                $amount = app(NutritionCalculator::class)->rawGramsFromCooked(
                    app(UnitResolver::class)->toGrams($food, $amount, $unit),
                    $food->cooked_yield,
                );
                $unit = 'g';
            } catch (UnknownUnitException|InvalidQuantityException) {
                // keep what was typed; the row shows the unit error
            }
        }

        $this->ingredients[] = [
            'key' => (string) Str::uuid(),
            'food_id' => $food->id,
            'quantity' => $amount,
            'unit' => $unit,
        ];

        $this->resetErrorBag('ingredients');
        $this->dispatch('close-modal', name: self::PICKER_SHEET);
        $this->toast(__('recipes.ingredient_added', ['name' => $food->displayName()]));
    }

    public function removeIngredient(string $key): void
    {
        $this->ingredients = array_values(array_filter(
            $this->ingredients, fn (array $row) => $row['key'] !== $key,
        ));
        $this->resetErrorBag();
    }

    /** Move a row one place up (−1) or down (+1). */
    public function moveIngredient(string $key, int $direction): void
    {
        $from = array_search($key, array_column($this->ingredients, 'key'), true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || ! isset($this->ingredients[$to])) {
            return;
        }

        [$this->ingredients[$from], $this->ingredients[$to]] = [$this->ingredients[$to], $this->ingredients[$from]];
        $this->resetErrorBag();
    }

    public function save(RecipeService $service)
    {
        $recipe = null;
        if ($this->recipeId !== null) {
            $recipe = Recipe::findOrFail($this->recipeId);
            $this->authorize('update', $recipe);
        }

        $this->normalizeNumbers();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'servings' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'cookedWeight' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.food_id' => ['required', 'integer'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients.*.unit' => ['required', 'string', 'max:30'],
        ], [
            'ingredients.required' => __('recipes.errors.no_ingredients'),
            'ingredients.min' => __('recipes.errors.no_ingredients'),
        ], [
            'name' => __('recipes.fields.name'),
            'description' => __('recipes.fields.description'),
            'instructions' => __('recipes.fields.instructions'),
            'servings' => __('recipes.fields.servings'),
            'cookedWeight' => __('recipes.fields.cooked_weight'),
            'ingredients' => __('recipes.ingredients'),
            'ingredients.*.quantity' => __('recipes.fields.quantity'),
            'ingredients.*.unit' => __('recipes.fields.unit'),
        ]);

        try {
            $saved = $service->save(auth()->user(), [
                'name' => trim($this->name),
                'description' => trim($this->description) === '' ? null : $this->description,
                'instructions' => trim($this->instructions) === '' ? null : $this->instructions,
                'servings' => $this->servings,
                'cooked_weight_g' => $this->cookedWeight,
                'ingredients' => array_map(fn (array $row) => [
                    'food_id' => $row['food_id'],
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'],
                ], $this->ingredients),
            ], $recipe);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages($this->mapServiceErrors($e->errors()));
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        $this->flashToast(__($recipe === null ? 'recipes.created' : 'recipes.updated'));

        return $this->redirect(route('recipes.show', $saved), navigate: true);
    }

    public function render(RecipeService $service, UnitResolver $units, NutritionCalculator $calculator)
    {
        $foods = Food::query()->visibleTo(auth()->user())->with('units')
            ->whereIn('id', array_column($this->ingredients, 'food_id'))
            ->get()->keyBy('id');

        $rows = [];
        $draftRows = [];

        foreach ($this->ingredients as $index => $row) {
            $food = $foods->get((int) $row['food_id']);
            $quantity = Amount::parse($row['quantity']);
            $grams = null;
            $contribution = null;

            if ($food !== null && $quantity !== null) {
                try {
                    $grams = $units->toGrams($food, $quantity, (string) $row['unit']);
                    $contribution = $calculator->forGrams($food->per100g(), $grams);
                    $draftRows[] = ['food' => $food, 'quantity' => $quantity, 'unit' => $row['unit'], 'grams' => $grams];
                } catch (UnknownUnitException|InvalidQuantityException) {
                    // row is left out of the panel and flagged below
                }
            }

            $rows[] = [
                'index' => $index,
                'key' => $row['key'],
                'food' => $food,
                'units' => $food === null ? [] : Amount::unitOptions($food),
                'grams' => $grams,
                'contribution' => $contribution,
            ];
        }

        $servings = Amount::parse($this->servings);
        $cookedWeight = Amount::parse($this->cookedWeight);

        $draft = $draftRows !== [] && $servings !== null
            ? DraftRecipe::make($servings, $cookedWeight, $draftRows)
            : null;

        return view('livewire.recipes.form', [
            'rows' => $rows,
            'nutrition' => $draft === null ? null : $service->nutrition($draft),
            'estimate' => $draft !== null && $service->isEstimate($draft),
            'skipped' => count($rows) - count($draftRows),
            'editing' => $this->recipeId !== null,
            'pickerAvailable' => class_exists(Picker::class),
        ])
            ->title(__($this->recipeId === null ? 'recipes.create_title' : 'recipes.edit_title'))
            ->layoutData([
                'wide' => true,
                'back' => $this->recipeId === null ? route('recipes.index') : route('recipes.show', $this->recipeId),
            ]);
    }

    private function normalizeNumbers(): void
    {
        $clean = fn ($value) => is_string($value) ? str_replace(',', '.', trim(Format::westernDigits($value))) : $value;

        $this->servings = $clean($this->servings);
        $this->cookedWeight = $clean($this->cookedWeight);
        if ($this->cookedWeight === '') {
            $this->cookedWeight = null;
        }

        foreach ($this->ingredients as $index => $row) {
            $this->ingredients[$index]['quantity'] = $clean($row['quantity']);
        }
    }

    /**
     * RecipeService reports errors with its own data keys; put them on this form's fields.
     * `ingredients.N.*` keeps its index because the rows are sent in the order they are shown.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function mapServiceErrors(array $errors): array
    {
        $mapped = [];

        foreach ($errors as $key => $messages) {
            $mapped[$key === 'cooked_weight_g' ? 'cookedWeight' : $key] = $messages;
        }

        return $mapped;
    }
}
