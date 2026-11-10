<?php

namespace App\Livewire\Plans;

use App\Enums\Meal;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Foods\Picker;
use App\Livewire\Plans\Support\ItemNutrition;
use App\Livewire\Recipes\Support\Amount;
use App\Models\Food;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Services\Food\UnitResolver;
use App\Services\Plans\MealPlanService;
use App\View\Format;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Create / edit a meal plan. Items are kept as (day offset, meal, food|recipe, quantity, unit) so that
 * moving the start date moves the whole plan. Foods arrive from the shared picker (`food-picked`,
 * context "plan"); recipes from the recipe sheet. Totals are produced by the engine
 * (see Support\ItemNutrition) and compared with profile->targets().
 */
class Form extends Component
{
    use AuthorizesRequests, Toasts;

    public const PICKER_CONTEXT = 'plan';

    public const FOOD_SHEET = 'plan-food-picker';

    public const RECIPE_SHEET = 'plan-recipe-picker';

    public const COPY_SHEET = 'plan-copy-day';

    #[Locked]
    public ?int $planId = null;

    public string $name = '';

    public string $startsOn = '';

    public $days = 7;

    public int $activeDay = 0;

    /** Meal slot the next picked food / recipe goes into. */
    public string $addMeal = 'breakfast';

    public int $copyTo = 1;

    /** @var list<array{key: string, id: ?int, day: int, meal: string, food_id: ?int, recipe_id: ?int, quantity: mixed, unit: string, note: ?string}> */
    public array $items = [];

    public function mount($plan = null): void
    {
        $this->startsOn = today()->toDateString();
        $this->name = __('plans.default_name');

        if ($plan === null) {
            return;
        }

        $plan = $plan instanceof MealPlan ? $plan : MealPlan::findOrFail($plan);
        $this->authorize('update', $plan);

        $this->planId = $plan->id;
        $this->name = $plan->name;
        $this->startsOn = $plan->starts_on->toDateString();
        $this->days = $plan->days;
        $this->items = $plan->items()->orderBy('id')->get()->map(fn ($item) => [
            'key' => (string) Str::uuid(),
            'id' => $item->id,
            'day' => (int) $plan->starts_on->diffInDays($item->planned_on),
            'meal' => $item->meal->value,
            'food_id' => $item->food_id,
            'recipe_id' => $item->recipe_id,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'note' => $item->note,
        ])->all();
    }

    public function selectDay(int $day): void
    {
        $this->activeDay = max(0, min($day, $this->dayCount() - 1));
    }

    public function updatedDays(): void
    {
        $count = $this->dayCount();
        $before = count($this->items);

        $this->items = array_values(array_filter($this->items, fn (array $item) => $item['day'] < $count));
        $this->activeDay = min($this->activeDay, $count - 1);
        $this->copyTo = min($this->copyTo, $count - 1);

        if (count($this->items) < $before) {
            $this->toast(__('plans.items_dropped'), 'warning');
        }
    }

    public function openFoodPicker(string $meal): void
    {
        $this->addMeal = Meal::tryFrom($meal)?->value ?? 'breakfast';

        $this->dispatch('food-picker-reset', context: self::PICKER_CONTEXT, meal: $this->addMeal);
        $this->dispatch('open-modal', name: self::FOOD_SHEET);
    }

    public function openRecipePicker(string $meal): void
    {
        $this->addMeal = Meal::tryFrom($meal)?->value ?? 'breakfast';
        $this->dispatch('open-modal', name: self::RECIPE_SHEET);
    }

    #[On('food-picked')]
    public function addFood(string $context = '', ?int $foodId = null, mixed $quantity = null, ?string $unit = null, bool $weighedCooked = false, ?string $meal = null): void
    {
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

        $this->items[] = $this->newItem($meal ?: $this->addMeal, $food->id, null, $amount, $unit);

        $this->dispatch('close-modal', name: self::FOOD_SHEET);
        $this->toast(__('plans.item_added', ['name' => $food->displayName()]));
    }

    public function addRecipe(int $recipeId): void
    {
        $recipe = Recipe::query()->where('user_id', auth()->id())->find($recipeId);
        if ($recipe === null) {
            return;
        }

        $this->items[] = $this->newItem($this->addMeal, null, $recipe->id, 1.0, 'serving');

        $this->dispatch('close-modal', name: self::RECIPE_SHEET);
        $this->toast(__('plans.item_added', ['name' => $recipe->name]));
    }

    public function removeItem(string $key): void
    {
        $this->items = array_values(array_filter($this->items, fn (array $item) => $item['key'] !== $key));
        $this->resetErrorBag();
    }

    public function openCopy(): void
    {
        $this->copyTo = $this->activeDay + 1 < $this->dayCount() ? $this->activeDay + 1 : max(0, $this->activeDay - 1);
        $this->dispatch('open-modal', name: self::COPY_SHEET);
    }

    /** Replace the contents of another day with a copy of the active day. */
    public function copyDay(): void
    {
        $from = $this->activeDay;
        $to = max(0, min($this->copyTo, $this->dayCount() - 1));

        if ($from === $to) {
            return;
        }

        $copies = collect($this->items)->where('day', $from)
            ->map(fn (array $item) => [...$item, 'key' => (string) Str::uuid(), 'id' => null, 'day' => $to])
            ->values()->all();

        // The replaced day's items disappear (a logged item loses its link); the copies are new.
        $this->items = [...array_values(array_filter($this->items, fn (array $item) => $item['day'] !== $to)), ...$copies];

        $this->dispatch('close-modal', name: self::COPY_SHEET);
        $this->toast(__('plans.day_copied', ['day' => $this->dayLabel($to)]));
    }

    public function save(MealPlanService $service)
    {
        $plan = null;
        if ($this->planId !== null) {
            $plan = MealPlan::findOrFail($this->planId);
            $this->authorize('update', $plan);
        }

        $this->days = is_string($this->days) ? Format::westernDigits(trim($this->days)) : $this->days;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'startsOn' => ['required', 'date'],
            'days' => ['required', 'integer', 'between:1,14'],
        ], [], [
            'name' => __('plans.fields.name'),
            'startsOn' => __('plans.fields.starts_on'),
            'days' => __('plans.fields.days'),
        ]);

        foreach ($this->items as $index => $item) {
            $this->items[$index]['quantity'] = is_string($item['quantity'])
                ? str_replace(',', '.', trim(Format::westernDigits($item['quantity'])))
                : $item['quantity'];
        }

        $start = CarbonImmutable::parse($this->startsOn)->startOfDay();

        // Rows are sent in display order, so `items.N.*` errors map straight back onto rows.
        $payload = array_map(fn (array $item) => array_filter([
            'id' => $item['id'],
            'planned_on' => $start->addDays($item['day'])->toDateString(),
            'meal' => $item['meal'],
            'food_id' => $item['food_id'],
            'recipe_id' => $item['recipe_id'],
            'quantity' => $item['quantity'],
            'unit' => $item['unit'],
            'note' => $item['note'],
        ], fn ($value) => $value !== null), $this->items);

        try {
            $saved = $service->save(auth()->user(), [
                'name' => trim($this->name),
                'starts_on' => $start->toDateString(),
                'days' => (int) $this->days,
                'items' => $payload,
            ], $plan);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(
                fn ($messages, $key) => [preg_replace('/^items\.(\d+)\.(food_id|recipe_id|planned_on|id)$/', 'items.$1.quantity', $key) => $messages]
            )->all());
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        $this->flashToast(__($plan === null ? 'plans.created' : 'plans.updated'));

        return $this->redirect(route('plans.show', $saved), navigate: true);
    }

    public function render(ItemNutrition $math)
    {
        $user = auth()->user();
        $count = $this->dayCount();
        $start = $this->start();

        $foods = Food::query()->visibleTo($user)->with('units')
            ->whereIn('id', array_filter(array_column($this->items, 'food_id')))
            ->get()->keyBy('id');
        $recipes = Recipe::query()->where('user_id', $user->id)
            ->whereIn('id', array_filter(array_column($this->items, 'recipe_id')))
            ->get()->keyBy('id');

        $rows = [];
        foreach ($this->items as $index => $item) {
            $food = $item['food_id'] === null ? null : $foods->get($item['food_id']);
            $recipe = $item['recipe_id'] === null ? null : $recipes->get($item['recipe_id']);
            $quantity = Amount::parse($item['quantity']);

            $rows[$index] = [
                'index' => $index,
                'key' => $item['key'],
                'day' => $item['day'],
                'meal' => $item['meal'],
                'name' => $food?->displayName() ?? $recipe?->name ?? __('plans.deleted_item'),
                'units' => $food !== null ? Amount::unitOptions($food) : Amount::recipeUnitOptions(),
                'is_recipe' => $item['recipe_id'] !== null,
                'nutrients' => $quantity === null ? null : $math->for($food, $recipe, $quantity, $item['unit']),
            ];
        }

        $dayList = [];
        for ($day = 0; $day < $count; $day++) {
            $dayRows = collect($rows)->where('day', $day);
            $totals = $math->total($dayRows->pluck('nutrients'));

            $dayList[$day] = [
                'index' => $day,
                'date' => $start->addDays($day),
                'totals' => $totals,
                'count' => $dayRows->count(),
                'over' => $user->profile !== null && $dayRows->isNotEmpty() && $totals->netCarbs() > $user->profile->targets()->netCarbs,
            ];
        }

        $day = $dayList[min($this->activeDay, $count - 1)];

        return view('livewire.plans.form', [
            'dayList' => $dayList,
            'day' => $day,
            'rows' => $rows,
            'meals' => Meal::cases(),
            'targets' => $user->profile?->targets(),
            'recipeList' => Recipe::query()->where('user_id', $user->id)->orderBy('name')->get(['id', 'name', 'servings']),
            'copyOptions' => collect($dayList)->except($day['index'])->mapWithKeys(fn (array $d) => [$d['index'] => $this->dayLabel($d['index'])])->all(),
            'editing' => $this->planId !== null,
            'pickerAvailable' => class_exists(Picker::class),
        ])
            ->title(__($this->planId === null ? 'plans.create_title' : 'plans.edit_title'))
            ->layoutData([
                'wide' => true,
                'back' => $this->planId === null ? route('plans.index') : route('plans.show', $this->planId),
            ]);
    }

    private function dayCount(): int
    {
        $days = Amount::parse($this->days);

        return (int) max(1, min(14, $days === null ? 1 : floor($days)));
    }

    private function start(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->startsOn)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    private function dayLabel(int $day): string
    {
        return __('plans.day_n', ['n' => Format::number($day + 1)]).' · '.Format::date($this->start()->addDays($day), 'short');
    }

    private function newItem(string $meal, ?int $foodId, ?int $recipeId, float $quantity, string $unit): array
    {
        return [
            'key' => (string) Str::uuid(),
            'id' => null,
            'day' => min($this->activeDay, $this->dayCount() - 1),
            'meal' => Meal::tryFrom($meal)?->value ?? 'breakfast',
            'food_id' => $foodId,
            'recipe_id' => $recipeId,
            'quantity' => $quantity,
            'unit' => $unit,
            'note' => null,
        ];
    }
}
