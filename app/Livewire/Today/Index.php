<?php

namespace App\Livewire\Today;

use App\Domain\Nutrition\Nutrients;
use App\Enums\Meal;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Foods\Picker;
use App\Livewire\Foods\Support\FoodHistory;
use App\Livewire\Foods\Support\NutritionErrors;
use App\Livewire\Foods\Support\UnitLabels;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\FoodLogService;
use App\View\Format;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * The diary for one day: remaining macros, entries by meal, what to eat next, quick add.
 * Every number comes from DailySummaryService / FoodLogService; this class only moves state around.
 */
class Index extends Component
{
    use AuthorizesRequests, NutritionErrors, Toasts;

    public const PICKER_CONTEXT = 'today';

    /** 'Y-m-d' of the day shown; '' = today (keeps the URL clean). Never a future date. */
    #[Url(as: 'date', except: '')]
    public string $date = '';

    /** Meal the add / recipe sheets log into. */
    public string $addMeal = 'breakfast';

    #[Locked]
    public ?int $editingId = null;

    /** @var int|float|string|null */
    public $editQuantity = 1;

    public string $editUnit = 'g';

    public string $editMeal = 'breakfast';

    public string $editDate = '';

    #[Locked]
    public ?int $deletingId = null;

    #[Locked]
    public ?int $recipeId = null;

    /** @var int|float|string|null */
    public $recipeServings = 1;

    public function mount(): void
    {
        $this->date = $this->normalizeDate($this->date);
        $this->addMeal = $this->defaultMeal()->value;
    }

    // ── Date navigation ────────────────────────────────────────────────────────────────────

    public function updatedDate(): void
    {
        $this->date = $this->normalizeDate($this->date);
    }

    public function goTo(string $date): void
    {
        $this->date = $this->normalizeDate($date);
    }

    public function previousDay(): void
    {
        $this->date = $this->normalizeDate($this->day()->subDay()->toDateString());
    }

    public function nextDay(): void
    {
        $this->date = $this->normalizeDate($this->day()->addDay()->toDateString());
    }

    public function goToday(): void
    {
        $this->date = '';
    }

    // ── Add ───────────────────────────────────────────────────────────────────────────────

    /** "Add" on a meal: empty picker in the add sheet. */
    public function openAdd(string $meal): void
    {
        $this->addMeal = (Meal::tryFrom($meal) ?? $this->defaultMeal())->value;

        $this->dispatch('food-picker-reset', context: self::PICKER_CONTEXT)->to(Picker::class);
        $this->dispatch('open-modal', name: 'add-food');
    }

    /** A suggested food: add sheet on the quantity step at the food's default amount. */
    public function suggestFood(int $foodId): void
    {
        $this->addMeal = $this->defaultMeal()->value;

        $this->dispatch('food-picker-select', context: self::PICKER_CONTEXT, foodId: $foodId)->to(Picker::class);
        $this->dispatch('open-modal', name: 'add-food');
    }

    /** A food from the user's history: add sheet prefilled with the amount logged last time. */
    public function again(int $entryId): void
    {
        $entry = $this->findEntry($entryId);
        $this->authorize('view', $entry);

        if ($entry->food_id === null) {
            return;
        }

        $this->addMeal = $this->defaultMeal()->value;

        $this->dispatch('food-picker-select',
            context: self::PICKER_CONTEXT,
            foodId: $entry->food_id,
            quantity: $entry->quantity,
            unit: $entry->unit,
            weighedCooked: (bool) $entry->weighed_cooked,
        )->to(Picker::class);
        $this->dispatch('open-modal', name: 'add-food');
    }

    /** One tap: log the same amount as last time into the meal that fits the time of day. */
    public function repeat(int $entryId): void
    {
        $entry = $this->findEntry($entryId);
        $this->authorize('view', $entry);

        $food = $entry->food_id === null ? null : $this->findFood($entry->food_id);
        if ($food === null) {
            $this->toast(__('foods.errors.not_accessible'), 'error');

            return;
        }

        $this->logFood($food, (float) $entry->quantity, $entry->unit, (bool) $entry->weighed_cooked, $this->defaultMeal());
    }

    #[On('food-picked')]
    public function onFoodPicked(?string $context = null, ?int $foodId = null, mixed $quantity = null, ?string $unit = null, bool $weighedCooked = false): void
    {
        if ($context !== self::PICKER_CONTEXT || $foodId === null) {
            return;
        }

        $food = $this->findFood($foodId);
        $amount = $this->parseQuantity($quantity);

        if ($food === null || $amount === null || $unit === null) {
            $this->toast($food === null ? __('foods.errors.not_accessible') : __('nutrition_errors.invalid_quantity'), 'error');

            return;
        }

        $meal = Meal::tryFrom($this->addMeal) ?? $this->defaultMeal();

        if ($this->logFood($food, $amount, $unit, $weighedCooked, $meal)) {
            $this->dispatch('close-modal', name: 'add-food');
        }
    }

    /** A suggested recipe: small sheet asking for servings. */
    public function suggestRecipe(int $recipeId): void
    {
        $recipe = Recipe::query()->findOrFail($recipeId);
        $this->authorize('view', $recipe);

        $this->recipeId = $recipe->id;
        $this->recipeServings = 1;
        $this->addMeal = $this->defaultMeal()->value;
        $this->resetErrorBag();

        $this->dispatch('open-modal', name: 'add-recipe');
    }

    public function logRecipe(): void
    {
        $recipe = Recipe::query()->findOrFail($this->recipeId);
        $this->authorize('view', $recipe);

        $meal = Meal::tryFrom($this->addMeal) ?? $this->defaultMeal();

        try {
            $this->log()->logRecipe($this->user(), $recipe, $this->parseQuantity($this->recipeServings) ?? 0.0, 'serving', $meal, $this->day());
        } catch (Throwable $e) {
            $this->addError('recipeServings', $this->nutritionError($e));

            return;
        }

        $this->recipeId = null;
        $this->toast(__('today.added', ['name' => $recipe->name, 'meal' => $meal->label()]));
        $this->dispatch('close-modal', name: 'add-recipe');
    }

    // ── Edit / delete ─────────────────────────────────────────────────────────────────────

    public function edit(int $entryId): void
    {
        $entry = $this->findEntry($entryId);
        $this->authorize('update', $entry);

        $this->editingId = $entry->id;
        $this->editQuantity = floor($entry->quantity) === $entry->quantity ? (int) $entry->quantity : $entry->quantity;
        $this->editUnit = $entry->unit;
        $this->editMeal = $entry->meal->value;
        $this->editDate = $entry->logged_on->toDateString();
        $this->resetErrorBag();

        $this->dispatch('open-modal', name: 'edit-entry');
    }

    public function saveEdit(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $entry = $this->findEntry($this->editingId);
        $this->authorize('update', $entry);

        $this->validate([
            'editMeal' => ['required', 'in:'.implode(',', Meal::values())],
            'editDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()->toDateString()],
        ], [
            'editDate.before_or_equal' => __('today.errors.future_date'),
        ], [
            'editMeal' => __('today.edit.meal'),
            'editDate' => __('today.edit.date'),
        ]);

        $quantity = $this->parseQuantity($this->editQuantity);
        if ($quantity === null || $quantity <= 0) {
            $this->addError('editQuantity', __('nutrition_errors.invalid_quantity'));

            return;
        }

        // Only what really changed is sent: moving an entry must not recalculate its snapshot.
        $changes = [];
        if (abs($quantity - $entry->quantity) > 1e-9) {
            $changes['quantity'] = $quantity;
        }
        if (UnitResolver::normalize($this->editUnit) !== $entry->unit) {
            $changes['unit'] = $this->editUnit;
        }
        if ($this->editMeal !== $entry->meal->value) {
            $changes['meal'] = $this->editMeal;
        }
        if ($this->editDate !== $entry->logged_on->toDateString()) {
            $changes['logged_on'] = $this->editDate;
        }

        if ($changes !== []) {
            try {
                $this->log()->update($entry, $changes);
            } catch (Throwable $e) {
                $this->addError($this->editErrorField($e), $this->nutritionError($e));

                return;
            }
        }

        $this->editingId = null;
        $this->dispatch('close-modal', name: 'edit-entry');
        $this->toast(isset($changes['logged_on'])
            ? __('today.moved', ['date' => Format::date($this->editDate, 'weekday')])
            : __('today.updated'));
    }

    /** From the edit sheet or a row: ask before deleting. */
    public function confirmDelete(?int $entryId = null): void
    {
        $entry = $this->findEntry($entryId ?? $this->editingId ?? 0);
        $this->authorize('delete', $entry);

        $this->deletingId = $entry->id;
        $this->editingId = null;

        $this->dispatch('close-modal', name: 'edit-entry');
        $this->dispatch('open-modal', name: 'delete-entry');
    }

    public function delete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $entry = $this->findEntry($this->deletingId);
        $this->authorize('delete', $entry);

        $this->log()->delete($entry);

        $this->deletingId = null;
        $this->dispatch('close-modal', name: 'delete-entry');
        $this->toast(__('today.deleted'));
    }

    // ── Render ────────────────────────────────────────────────────────────────────────────

    public function render(DailySummaryService $summaries, NextMealSuggester $suggester, FoodHistory $history, UnitResolver $units, FoodLogService $log)
    {
        $user = $this->user();
        $day = $this->day();

        $summary = $summaries->forDate($user, $day);
        $summary->entries->loadMissing('food.units');

        $editing = $this->editingId === null ? null : $summary->entries->firstWhere('id', $this->editingId)
            ?? FoodLogEntry::query()->where('user_id', $user->id)->with(['food.units', 'recipe'])->find($this->editingId);
        [$editPreview, $editError] = $editing === null ? [null, null] : $this->previewEdit($log, $editing);

        $deleting = $this->deletingId === null ? null : $summary->entries->firstWhere('id', $this->deletingId);

        $recipe = $this->recipeId === null ? null : Recipe::query()->where('user_id', $user->id)->find($this->recipeId);
        [$recipePreview, $recipeError] = $recipe === null ? [null, null] : $this->previewRecipe($log, $recipe);

        return view('livewire.today.index', [
            'day' => $day,
            'isToday' => $day->equalTo($this->today()),
            'maxDate' => $this->today()->toDateString(),
            'summary' => $summary,
            'missingFiber' => $summary->entries->filter(fn (FoodLogEntry $entry) => $entry->nutrients()->isMissing(Nutrients::FIBER))->count(),
            'missingSaturated' => $summary->entries->filter(fn (FoodLogEntry $entry) => $entry->nutrients()->isMissing(Nutrients::SATURATED_FAT))->count(),
            'suggestions' => $suggester->suggest($user, $summary),
            'recent' => $history->recent($user, 6),
            'frequent' => $history->frequent($user, 6),
            'mealOptions' => collect(Meal::cases())->mapWithKeys(fn (Meal $meal) => [$meal->value => $meal->label()])->all(),
            'editing' => $editing,
            'editUnits' => $editing === null ? [] : UnitLabels::optionsForEntry($editing, $units),
            'editPreview' => $editPreview,
            'editError' => $editError,
            'deleting' => $deleting,
            'recipe' => $recipe,
            'recipePreview' => $recipePreview,
            'recipeError' => $recipeError,
        ])->title(__('today.title'))->layoutData(['wide' => true]);
    }

    // ── Internals ─────────────────────────────────────────────────────────────────────────

    private function logFood(Food $food, float $quantity, string $unit, bool $weighedCooked, Meal $meal): bool
    {
        $this->authorize('view', $food);

        try {
            $this->log()->logFood($this->user(), $food, $quantity, $unit, $meal, $this->day(), weighedCooked: $weighedCooked);
        } catch (Throwable $e) {
            $this->toast($this->nutritionError($e), 'error');

            return false;
        }

        $this->toast(__('today.added', ['name' => $food->displayName(), 'meal' => $meal->label()]));

        return true;
    }

    /**
     * What the edited amount would be, calculated by the service (never here).
     *
     * @return array{0: ?Nutrients, 1: ?array{field: string, message: string}}
     */
    private function previewEdit(FoodLogService $log, FoodLogEntry $entry): array
    {
        $quantity = $this->parseQuantity($this->editQuantity);

        try {
            if ($quantity === null) {
                throw new InvalidQuantityException(0.0);
            }

            if ($entry->food_id !== null && $entry->food !== null) {
                return [$log->preview($entry->food, $quantity, $this->editUnit, (bool) $entry->weighed_cooked), null];
            }

            if ($entry->recipe_id !== null && $entry->recipe !== null) {
                return [$log->previewRecipe($entry->recipe, $quantity, $this->editUnit), null];
            }

            // Source deleted: the service scales the stored snapshot; show the same thing.
            InvalidQuantityException::assertValid($quantity);

            return [$entry->quantity > 0 ? $entry->nutrients()->scale($quantity / $entry->quantity) : $entry->nutrients(), null];
        } catch (Throwable $e) {
            return [null, ['field' => $this->editErrorField($e), 'message' => $this->nutritionError($e)]];
        }
    }

    /** @return array{0: ?Nutrients, 1: ?string} */
    private function previewRecipe(FoodLogService $log, Recipe $recipe): array
    {
        try {
            return [$log->previewRecipe($recipe, $this->parseQuantity($this->recipeServings) ?? 0.0, 'serving'), null];
        } catch (Throwable $e) {
            return [null, $this->nutritionError($e)];
        }
    }

    private function editErrorField(Throwable $e): string
    {
        return match (true) {
            $e instanceof InvalidQuantityException => 'editQuantity',
            $e instanceof UnknownUnitException => 'editUnit',
            default => 'editEntry',
        };
    }

    private function findEntry(int $entryId): FoodLogEntry
    {
        return FoodLogEntry::query()->with(['food.units', 'recipe'])->findOrFail($entryId);
    }

    private function findFood(int $foodId): ?Food
    {
        return Food::query()->visibleTo($this->user())->with('units')->find($foodId);
    }

    private function log(): FoodLogService
    {
        return app(FoodLogService::class);
    }

    private function user(): User
    {
        return Auth::user();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }

    private function day(): CarbonImmutable
    {
        return $this->date === '' ? $this->today() : CarbonImmutable::createFromFormat('!Y-m-d', $this->date);
    }

    /** Anything that is not a real past date collapses to '' (today). */
    private function normalizeDate(?string $date): string
    {
        $date = trim((string) $date);
        if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        [$year, $month, $dayOfMonth] = array_map('intval', explode('-', $date));
        if (! checkdate($month, $dayOfMonth, $year)) {
            return '';
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date)->lessThan($this->today()) ? $date : '';
    }

    /** The meal a quick add most likely belongs to, by time of day. */
    private function defaultMeal(): Meal
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 11 => Meal::Breakfast,
            $hour < 16 => Meal::Lunch,
            $hour < 22 => Meal::Dinner,
            default => Meal::Snack,
        };
    }
}
