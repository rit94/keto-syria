<?php

namespace App\Services\Plans;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MealPlanService
{
    public function __construct(
        private readonly NutritionCalculator $calculator,
        private readonly UnitResolver $units,
        private readonly FoodLogService $log,
    ) {}

    /**
     * Create a plan, or replace an existing one.
     *
     * $data: name, starts_on, days, origin, items[[planned_on, meal, food_id|recipe_id, quantity, unit, note]]
     *
     * Rules: every item references exactly one of food_id / recipe_id; foods must be visible to the
     * user and recipes owned by the user; planned_on must fall within starts_on .. starts_on + days − 1;
     * food units are resolved to grams with UnitResolver (default 'g'); recipes accept 'serving'
     * (default) or 'g'.
     *
     * When updating, an item may carry the `id` of an existing item of this plan: that row is updated
     * in place and keeps its "already eaten" link (food_log_entry_id). Existing items that are not
     * sent back are removed.
     *
     * @throws ValidationException
     * @throws AuthorizationException plan / recipe owned by someone else, or a food the user cannot see
     */
    public function save(User $user, array $data, ?MealPlan $plan = null): MealPlan
    {
        if ($plan !== null && (int) $plan->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('nutrition_errors.plan_not_accessible'));
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'days' => ['required', 'integer', 'between:1,366'],
            'origin' => ['sometimes', 'in:user,ai'],
            'items' => ['sometimes', 'array', 'max:2000'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.planned_on' => ['required', 'date'],
            'items.*.meal' => ['required', Rule::enum(Meal::class)],
            'items.*.food_id' => ['nullable', 'integer'],
            'items.*.recipe_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $items = array_values($validated['items'] ?? []);
        $startsOn = CarbonImmutable::parse($validated['starts_on'])->startOfDay();
        $endsOn = $startsOn->addDays((int) $validated['days'] - 1);

        $foods = Food::query()->visibleTo($user)->with('units')
            ->whereIn('id', array_filter(array_column($items, 'food_id')))
            ->get()->keyBy('id');
        $recipes = Recipe::query()->where('user_id', $user->id)
            ->whereIn('id', array_filter(array_column($items, 'recipe_id')))
            ->get()->keyBy('id');
        $existingIds = $plan?->items()->pluck('id')->all() ?? [];

        $errors = [];
        $rows = [];

        foreach ($items as $index => $item) {
            $foodId = $item['food_id'] ?? null;
            $recipeId = $item['recipe_id'] ?? null;

            if (($foodId === null) === ($recipeId === null)) {
                $errors["items.$index.food_id"] = __('nutrition_errors.one_of_food_or_recipe');

                continue;
            }

            $plannedOn = CarbonImmutable::parse($item['planned_on'])->startOfDay();
            if ($plannedOn->lessThan($startsOn) || $plannedOn->greaterThan($endsOn)) {
                $errors["items.$index.planned_on"] = __('nutrition_errors.outside_plan_range');

                continue;
            }

            $id = $item['id'] ?? null;
            if ($id !== null && ! in_array((int) $id, $existingIds, true)) {
                $errors["items.$index.id"] = __('nutrition_errors.unknown_plan_item');

                continue;
            }

            $quantity = (float) $item['quantity'];

            if ($foodId !== null) {
                $food = $foods->get((int) $foodId)
                    ?? throw new AuthorizationException(__('nutrition_errors.food_not_accessible'));
                $unit = UnitResolver::normalize($item['unit'] ?? 'g');

                try {
                    $grams = $this->units->toGrams($food, $quantity, $unit);
                } catch (UnknownUnitException $e) {
                    $errors["items.$index.unit"] = $e->getMessage();

                    continue;
                } catch (InvalidQuantityException $e) {
                    $errors["items.$index.quantity"] = $e->getMessage();

                    continue;
                }
            } else {
                if (! $recipes->has((int) $recipeId)) {
                    throw new AuthorizationException(__('nutrition_errors.recipe_not_accessible'));
                }
                $unit = UnitResolver::normalize($item['unit'] ?? 'serving');

                if (! in_array($unit, ['serving', 'g'], true)) {
                    $errors["items.$index.unit"] = (new UnknownUnitException($unit))->getMessage();

                    continue;
                }
                $grams = $unit === 'g' ? $quantity : null;
            }

            $rows[] = [
                'id' => $id === null ? null : (int) $id,
                'planned_on' => $plannedOn->toDateString(),
                'meal' => $item['meal'] instanceof Meal ? $item['meal'] : Meal::from($item['meal']),
                'food_id' => $foodId === null ? null : (int) $foodId,
                'recipe_id' => $recipeId === null ? null : (int) $recipeId,
                'quantity' => $quantity,
                'unit' => $unit,
                'grams' => $grams,
                'note' => $item['note'] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($user, $validated, $startsOn, $rows, $plan) {
            $plan ??= new MealPlan(['user_id' => $user->id]);

            $plan->fill([
                'name' => $validated['name'],
                'starts_on' => $startsOn->toDateString(),
                'days' => (int) $validated['days'],
                'origin' => $validated['origin'] ?? $plan->origin ?? 'user',
            ])->save();

            $keep = array_filter(array_column($rows, 'id'));
            $plan->items()->whereNotIn('id', $keep)->delete();

            foreach ($rows as $row) {
                $id = $row['id'];
                unset($row['id']);

                if ($id === null) {
                    $plan->items()->create($row);
                } else {
                    $plan->items()->whereKey($id)->firstOrFail()->update($row);
                }
            }

            return $plan->unsetRelation('items')->load('items');
        });
    }

    /**
     * Planned nutrition for one day of the plan, calculated by the engine from current facts.
     * Items whose food / recipe has been deleted contribute nothing.
     */
    public function dayNutrition(MealPlan $plan, CarbonInterface $date): Nutrients
    {
        $items = $plan->items()
            ->whereDate('planned_on', $date->toDateString())
            ->with(['food', 'recipe'])
            ->get();

        $parts = [];
        foreach ($items as $item) {
            if ($item->food !== null && $item->grams !== null) {
                $parts[] = $this->calculator->forGrams($item->food->per100g(), $item->grams);
            } elseif ($item->recipe !== null) {
                $parts[] = $this->log->previewRecipe($item->recipe, $item->quantity, $item->unit);
            }
        }

        return $this->calculator->dailyTotals($parts);
    }

    /**
     * Mark a planned item as eaten: writes a diary entry (source = plan) on the item's planned date
     * through FoodLogService and links it. Calling it again returns the existing entry.
     *
     * The caller authorises (MealPlanPolicy::update on the item's plan) before calling.
     *
     * @throws LoggingException the item's food / recipe was deleted
     */
    public function logItem(MealPlanItem $item): FoodLogEntry
    {
        $item->loadMissing(['plan.user', 'food', 'recipe']);

        $existing = $item->food_log_entry_id === null ? null : FoodLogEntry::find($item->food_log_entry_id);
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($item) {
            $user = $item->plan->user;

            if ($item->food !== null) {
                $entry = $this->log->logFood($user, $item->food, $item->quantity, $item->unit, $item->meal, $item->planned_on, LogSource::Plan);
            } elseif ($item->recipe !== null) {
                $entry = $this->log->logRecipe($user, $item->recipe, $item->quantity, $item->unit, $item->meal, $item->planned_on, LogSource::Plan);
            } else {
                throw LoggingException::planItemEmpty();
            }

            $item->update(['food_log_entry_id' => $entry->id]);

            return $entry;
        });
    }

    /** Diary entries already logged from the plan are kept. */
    public function delete(MealPlan $plan): void
    {
        $plan->delete();
    }
}
