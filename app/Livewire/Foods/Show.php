<?php

namespace App\Livewire\Foods;

use App\Enums\Meal;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Foods\Support\NutritionErrors;
use App\Models\Food;
use App\Services\Food\CustomFoodService;
use App\Services\Food\FoodInUseException;
use App\Services\Food\UnitResolver;
use App\Services\Logging\FoodLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class Show extends Component
{
    use AuthorizesRequests, NutritionErrors, Toasts;

    public const PICKER_CONTEXT = 'food-show';

    #[Locked]
    public int $foodId;

    public string $meal = 'lunch';

    public ?string $deleteError = null;

    public function mount(Food $food): void
    {
        $this->authorize('view', $food);
        $this->foodId = $food->id;
        $hour = (int) now()->format('G');
        $this->meal = ($hour < 11 ? Meal::Breakfast : ($hour < 16 ? Meal::Lunch : ($hour < 22 ? Meal::Dinner : Meal::Snack)))->value;
    }

    public function openLog(): void
    {
        $this->dispatch('food-picker-select', context: self::PICKER_CONTEXT, foodId: $this->foodId, meal: $this->meal)->to(Picker::class);
        $this->dispatch('open-modal', name: 'log-food');
    }

    #[On('food-picked')]
    public function onPicked(?string $context = null, ?int $foodId = null, mixed $quantity = null, ?string $unit = null, bool $weighedCooked = false): void
    {
        if ($context !== self::PICKER_CONTEXT || $foodId !== $this->foodId) {
            return;
        }

        $food = $this->food();
        $this->authorize('view', $food);
        $meal = Meal::tryFrom($this->meal) ?? Meal::Lunch;

        try {
            app(FoodLogService::class)->logFood(auth()->user(), $food, (float) $this->parseQuantity($quantity), (string) $unit, $meal, now(), weighedCooked: $weighedCooked);
        } catch (Throwable $e) {
            $this->toast($this->nutritionError($e), 'error');

            return;
        }

        $this->dispatch('close-modal', name: 'log-food');
        $this->toast(__('today.added', ['name' => $food->displayName(), 'meal' => $meal->label()]));
    }

    public function delete(CustomFoodService $service)
    {
        $food = $this->food();
        $this->authorize('delete', $food);

        try {
            $service->delete($food);
        } catch (FoodInUseException|AuthorizationException $e) {
            $this->deleteError = $e->getMessage();
            $this->dispatch('close-modal', name: 'delete-food');

            return;
        }

        $this->flashToast(__('foods.deleted'));

        return $this->redirectRoute('foods.index', navigate: true);
    }

    public function render(UnitResolver $units)
    {
        $food = $this->food();
        $family = $food->parent_food_id ?? $food->id;

        $variants = Food::query()->visibleTo(auth()->user())
            ->where(fn ($q) => $q->where('id', $family)->orWhere('parent_food_id', $family))
            ->where('id', '!=', $food->id)
            ->orderBy('id')->get();

        return view('livewire.foods.show', [
            'food' => $food,
            'variants' => $variants,
            'units' => collect($units->unitsFor($food))->reject(fn ($u) => in_array($u['unit'], ['g', 'kg', 'oz'], true))->values(),
            'canEdit' => auth()->user()->can('update', $food),
            'mealOptions' => collect(Meal::cases())->mapWithKeys(fn (Meal $m) => [$m->value => $m->label()])->all(),
        ])->title($food->displayName())->layoutData(['back' => route('foods.index')]);
    }

    private function food(): Food
    {
        return Food::query()->with(['units', 'aliases'])->findOrFail($this->foodId);
    }
}
