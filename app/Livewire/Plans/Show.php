<?php

namespace App\Livewire\Plans;

use App\Enums\Meal;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Plans\Support\ItemNutrition;
use App\Livewire\Recipes\Support\Amount;
use App\Models\FoodLogEntry;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Services\Logging\LoggingException;
use App\Services\Plans\MealPlanService;
use App\Services\Plans\ShoppingListBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests, Toasts;

    public const DELETE_MODAL = 'delete-plan';

    #[Locked]
    public int $planId;

    public int $activeDay = 0;

    public string $tab = 'plan';

    public function mount($plan): void
    {
        $plan = $plan instanceof MealPlan ? $plan : MealPlan::findOrFail($plan);
        $this->authorize('view', $plan);

        $this->planId = $plan->id;

        $offset = (int) $plan->starts_on->diffInDays(today(), false);
        $this->activeDay = $offset >= 0 && $offset < $plan->days ? $offset : 0;
    }

    public function selectDay(int $day): void
    {
        $plan = $this->plan('view');
        $this->activeDay = max(0, min($day, $plan->days - 1));
    }

    public function logItem(int $itemId, MealPlanService $service): void
    {
        $plan = $this->plan('update');
        $item = $plan->items->firstWhere('id', $itemId);

        if ($item === null) {
            abort(404);
        }

        $this->logOne($service, $item);
    }

    public function logMeal(string $meal, MealPlanService $service): void
    {
        $plan = $this->plan('update');
        $date = $plan->starts_on->copy()->addDays($this->activeDay)->toDateString();

        $items = $plan->items->filter(fn (MealPlanItem $item) => $item->meal->value === $meal && $item->planned_on->toDateString() === $date);

        $logged = 0;
        foreach ($items as $item) {
            if ($item->food_log_entry_id === null || ! FoodLogEntry::whereKey($item->food_log_entry_id)->exists()) {
                $logged += $this->logOne($service, $item, quiet: true) ? 1 : 0;
            }
        }

        $logged > 0
            ? $this->toast(__('plans.show.meal_logged', ['meal' => Meal::from($meal)->label()]))
            : $this->toast(__('plans.show.nothing_to_log'), 'info');
    }

    public function delete(MealPlanService $service)
    {
        $service->delete($this->plan('delete'));

        $this->flashToast(__('plans.deleted'));

        return $this->redirect(route('plans.index'), navigate: true);
    }

    public function render(MealPlanService $service, ItemNutrition $math, ShoppingListBuilder $shopping)
    {
        $plan = $this->plan('view');
        $user = auth()->user();
        $targets = $user->profile?->targets();

        $date = $plan->starts_on->copy()->addDays($this->activeDay);

        $days = [];
        for ($d = 0; $d < $plan->days; $d++) {
            $dayDate = $plan->starts_on->copy()->addDays($d);
            $totals = $service->dayNutrition($plan, $dayDate);
            $count = $plan->items->filter(fn ($i) => $i->planned_on->isSameDay($dayDate))->count();

            $days[$d] = [
                'index' => $d,
                'date' => $dayDate,
                'totals' => $totals,
                'count' => $count,
                'over' => $targets !== null && $count > 0 && $totals->netCarbs() > $targets->netCarbs,
            ];
        }

        $loggedIds = FoodLogEntry::query()
            ->whereIn('id', $plan->items->pluck('food_log_entry_id')->filter())
            ->pluck('id')->flip();

        $dayItems = $plan->items->filter(fn ($i) => $i->planned_on->isSameDay($date));
        $meals = [];
        foreach (Meal::cases() as $meal) {
            $meals[$meal->value] = [
                'meal' => $meal,
                'items' => $dayItems->filter(fn ($i) => $i->meal === $meal)->map(function (MealPlanItem $item) use ($math, $loggedIds) {
                    return [
                        'item' => $item,
                        'name' => $item->food?->displayName() ?? $item->recipe?->name ?? __('plans.deleted_item'),
                        'quantity' => $item->quantity,
                        'unit' => $item->recipe !== null && $item->unit === 'serving'
                            ? __('units.serving')
                            : Amount::unitLabel($item->food, $item->unit),
                        'nutrients' => $math->for($item->food, $item->recipe, $item->quantity, $item->unit),
                        'logged' => $item->food_log_entry_id !== null && $loggedIds->has($item->food_log_entry_id),
                        'available' => $item->food !== null || $item->recipe !== null,
                    ];
                })->values(),
            ];
        }

        return view('livewire.plans.show', [
            'plan' => $plan,
            'ends' => $plan->starts_on->copy()->addDays($plan->days - 1),
            'days' => $days,
            'day' => $days[min($this->activeDay, $plan->days - 1)],
            'meals' => $meals,
            'targets' => $targets,
            'shoppingList' => $this->tab === 'shopping' ? $shopping->build($plan) : collect(),
            'today' => today(),
        ])
            ->title($plan->name)
            ->layoutData(['wide' => true, 'back' => route('plans.index')]);
    }

    private function logOne(MealPlanService $service, MealPlanItem $item, bool $quiet = false): bool
    {
        try {
            $service->logItem($item);
        } catch (LoggingException $e) {
            $this->toast($e->getMessage(), 'error');

            return false;
        }

        if (! $quiet) {
            $this->toast(__('plans.show.logged'));
        }

        return true;
    }

    /** Reloaded and re-authorised on every request: the id is locked, the policy decides. */
    private function plan(string $ability): MealPlan
    {
        $plan = MealPlan::with(['items.food.units', 'items.recipe'])->findOrFail($this->planId);
        $this->authorize($ability, $plan);

        return $plan;
    }
}
