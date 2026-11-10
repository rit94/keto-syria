<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\User;
use App\Services\Ai\Support\AiClock;
use App\Services\Ai\Support\FoodPresenter;
use App\Services\Plans\MealPlanService;

class GetMealPlanTool extends AbstractTool
{
    private const MAX_DAYS = 31;

    public function __construct(
        private readonly MealPlanService $plans,
        private readonly FoodPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'get_meal_plan';
    }

    public function description(): string
    {
        return 'The user\'s saved meal plans that overlap a date range: planned items per day (food or recipe, amount, meal, whether already eaten) '
            .'and the planned nutrition of each day. Call it when the user asks what is planned, or before proposing changes to a plan.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD or "today". Defaults to today.'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to six days after "from". At most a month after "from".'],
        ]);
    }

    protected function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'string', 'max:20'],
            'to' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $from = AiClock::parseDate($data['from'] ?? null);
        $to = isset($data['to']) && $data['to'] !== '' ? AiClock::parseDate($data['to']) : $from?->addDays(6);

        if ($from === null || $to === null || $to->lessThan($from)) {
            return self::error('invalid_date', 'Use YYYY-MM-DD dates with "to" on or after "from".');
        }
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $to = $from->addDays(self::MAX_DAYS - 1);
        }

        $plans = MealPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->orderBy('starts_on')
            ->with(['items' => fn ($q) => $q
                ->whereDate('planned_on', '>=', $from->toDateString())
                ->whereDate('planned_on', '<=', $to->toDateString())
                ->orderBy('planned_on')->orderBy('id')
                ->with(['food', 'recipe'])])
            ->get()
            ->filter(fn (MealPlan $plan) => $plan->starts_on->copy()->addDays($plan->days - 1)->toDateString() >= $from->toDateString())
            ->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'plan_count' => $plans->count(),
            'plans' => $plans->map(fn (MealPlan $plan) => [
                'meal_plan_id' => $plan->id,
                'name' => $plan->name,
                'starts_on' => $plan->starts_on->toDateString(),
                'days' => $plan->days,
                'by_day' => $plan->items
                    ->groupBy(fn (MealPlanItem $item) => $item->planned_on->toDateString())
                    ->map(fn ($items, string $day) => [
                        'date' => $day,
                        'items' => $items->map(fn (MealPlanItem $item) => [
                            'meal' => $item->meal->value,
                            'food_id' => $item->food_id,
                            'recipe_id' => $item->recipe_id,
                            'name' => $item->food?->name_ar ?? $item->recipe?->name,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'note' => $item->note,
                            'already_eaten' => $item->food_log_entry_id !== null,
                        ])->values()->all(),
                        'planned_totals' => $this->presenter->nutrients($this->plans->dayNutrition($plan, $items->first()->planned_on)),
                    ])->values()->all(),
            ])->all(),
        ];
    }
}
