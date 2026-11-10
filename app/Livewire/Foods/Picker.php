<?php

namespace App\Livewire\Foods;

use App\Domain\Nutrition\Nutrients;
use App\Enums\FoodCategory;
use App\Enums\FoodState;
use App\Livewire\Foods\Support\FoodHistory;
use App\Livewire\Foods\Support\NutritionErrors;
use App\Livewire\Foods\Support\UnitLabels;
use App\Models\Food;
use App\Services\Food\FoodSearchService;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\FoodLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Reusable food picker: search → (optional) quantity step → `food-picked` event.
 *
 *   <livewire:foods.picker :context="'recipe-ingredient'" :with-quantity="true" />
 *
 * Props
 *   context       string   opaque value echoed back in every event, so a page with several
 *                          pickers (or several listeners) can tell them apart
 *   withQuantity  bool     true (default): ask for quantity + unit before confirming
 *                          false: dispatch as soon as a food is tapped (quantity / unit null)
 *   meal          ?string  Meal value the food is being added to (shown as a hint, echoed back)
 *   foodId        ?int     start on the quantity step of this food
 *
 * Emits (Livewire event, named params)
 *   food-picked   context, foodId, quantity (?float), unit (?string), weighedCooked (bool), meal (?string)
 *
 * Listens to
 *   food-picker-select   context, foodId, quantity?, unit?, weighedCooked?   → jump to the quantity step, prefilled
 *   food-picker-reset    context?                                            → back to an empty search
 *
 * The picker never writes anything: the parent logs / stores the pick. Numbers shown here come from
 * Food::per100g() and FoodLogService::preview().
 */
class Picker extends Component
{
    use NutritionErrors;

    private const LIMIT = 20;

    private const RECENT_LIMIT = 6;

    #[Locked]
    public string $context = '';

    #[Locked]
    public bool $withQuantity = true;

    #[Locked]
    public ?string $meal = null;

    public string $query = '';

    public string $category = '';

    #[Locked]
    public ?int $foodId = null;

    /** @var int|float|string|null as typed; parsed with parseQuantity() */
    public $quantity = 100;

    public string $unit = 'g';

    public bool $weighedCooked = false;

    public function mount(string $context = '', bool $withQuantity = true, ?string $meal = null, ?int $foodId = null): void
    {
        $this->context = $context;
        $this->withQuantity = $withQuantity;
        $this->meal = $meal;

        if ($foodId !== null) {
            $this->start($foodId);
        }
    }

    /** A result was tapped. */
    public function select(int $foodId): void
    {
        $food = $this->findFood($foodId);
        if ($food === null) {
            $this->addError('query', __('foods.errors.not_accessible'));

            return;
        }

        if (! $this->withQuantity) {
            $this->dispatchPicked($food->id, null, null, false);

            return;
        }

        $this->start($food->id);
    }

    #[On('food-picker-select')]
    public function prefill(?string $context = null, ?int $foodId = null, mixed $quantity = null, ?string $unit = null, bool $weighedCooked = false, ?string $meal = null): void
    {
        if ($context !== $this->context || $foodId === null) {
            return;
        }

        if ($meal !== null) {
            $this->meal = $meal;
        }

        $this->start($foodId, $this->parseQuantity($quantity), $unit, $weighedCooked);
    }

    #[On('food-picker-reset')]
    public function resetPicker(?string $context = null, ?string $meal = null): void
    {
        if ($context !== null && $context !== $this->context) {
            return;
        }

        if ($meal !== null) {
            $this->meal = $meal;
        }

        $this->reset('query', 'category', 'foodId', 'quantity', 'unit', 'weighedCooked');
        $this->resetErrorBag();
    }

    /** Back from the quantity step to the results (the search text is kept). */
    public function back(): void
    {
        $this->reset('foodId', 'quantity', 'unit', 'weighedCooked');
        $this->resetErrorBag();
    }

    /** Switching unit starts from a sensible amount of the new unit (100 g, or 1 of anything else). */
    public function updatedUnit(): void
    {
        $this->quantity = $this->unit === 'g' ? 100 : 1;
    }

    public function confirm(): void
    {
        $food = $this->foodId === null ? null : $this->findFood($this->foodId);
        if ($food === null) {
            $this->back();

            return;
        }

        [$nutrients, $error] = $this->preview($food);
        if ($nutrients === null) {
            $this->addError($error['field'], $error['message']);

            return;
        }

        $this->dispatchPicked($food->id, $this->parseQuantity($this->quantity), UnitResolver::normalize($this->unit), $this->cookedApplies($food) && $this->weighedCooked);

        $this->reset('foodId', 'quantity', 'unit', 'weighedCooked', 'query');
    }

    public function render(FoodSearchService $search, FoodHistory $history, UnitResolver $units)
    {
        $user = Auth::user();
        $food = $this->foodId === null ? null : $this->findFood($this->foodId);

        if ($food !== null) {
            [$nutrients, $error] = $this->preview($food);

            return view('livewire.foods.picker', [
                'food' => $food,
                'unitOptions' => UnitLabels::options($food, $units),
                'unitLabel' => UnitLabels::for($food, $this->unit),
                'canWeighCooked' => $this->cookedApplies($food),
                'nutrients' => $nutrients,
                'previewError' => $error,
            ]);
        }

        $category = FoodCategory::tryFrom($this->category);
        $searching = trim($this->query) !== '';
        $recent = new Collection;

        if (! $searching && $category === null && $user !== null) {
            $recent = $history->recent($user, self::RECENT_LIMIT)->map->food;
        }

        $results = $search->search($this->query, $user, self::LIMIT, $category)
            ->reject(fn (Food $result) => $recent->contains('id', $result->id))
            ->values();

        return view('livewire.foods.picker', [
            'food' => null,
            'searching' => $searching,
            'recent' => $recent,
            'results' => $results,
            'categories' => FoodCategory::cases(),
        ]);
    }

    private function start(int $foodId, ?float $quantity = null, ?string $unit = null, bool $weighedCooked = false): void
    {
        $food = $this->findFood($foodId);
        if ($food === null) {
            return;
        }

        $units = app(UnitResolver::class);
        $default = UnitLabels::defaultAmount($food, $units);
        $known = array_key_exists(UnitResolver::normalize((string) $unit), UnitLabels::options($food, $units));

        $this->foodId = $food->id;
        $this->unit = $known ? UnitResolver::normalize($unit) : $default['unit'];
        $this->quantity = $this->tidy($known && $quantity !== null && $quantity > 0 ? $quantity : $default['quantity']);
        $this->weighedCooked = $weighedCooked && $this->cookedApplies($food);
        $this->resetErrorBag();
    }

    /**
     * @return array{0: ?Nutrients, 1: ?array{field: string, message: string}}
     */
    private function preview(Food $food): array
    {
        $quantity = $this->parseQuantity($this->quantity);

        try {
            if ($quantity === null) {
                throw new InvalidQuantityException(0.0);
            }

            return [
                app(FoodLogService::class)->preview($food, $quantity, $this->unit, $this->cookedApplies($food) && $this->weighedCooked),
                null,
            ];
        } catch (Throwable $e) {
            $field = match (true) {
                $e instanceof InvalidQuantityException => 'quantity',
                $e instanceof UnknownUnitException => 'unit',
                default => 'weighedCooked',
            };

            return [null, ['field' => $field, 'message' => $this->nutritionError($e)]];
        }
    }

    /** "Weighed after cooking" only means something for a raw food with a known cooked yield. */
    private function cookedApplies(Food $food): bool
    {
        return $food->state === FoodState::Raw && $food->cooked_yield !== null;
    }

    private function findFood(int $foodId): ?Food
    {
        return Food::query()->visibleTo(Auth::user())->with('units')->find($foodId);
    }

    private function dispatchPicked(int $foodId, ?float $quantity, ?string $unit, bool $weighedCooked): void
    {
        $this->dispatch('food-picked',
            context: $this->context,
            foodId: $foodId,
            quantity: $quantity,
            unit: $unit,
            weighedCooked: $weighedCooked,
            meal: $this->meal,
        );
    }

    /** 100.0 → 100 so the input does not show a trailing ".0". */
    private function tidy(float $quantity): int|float
    {
        return floor($quantity) === $quantity ? (int) $quantity : $quantity;
    }
}
