<?php

namespace App\Services\Ai\Proposals;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\Ai\Support\AiClock;
use App\Services\Ai\Support\FoodPresenter;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;
use App\Services\Recipes\RecipeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validates a proposed write and computes what it would produce – WITHOUT writing anything.
 *
 * Every number in a preview is produced by FoodLogService::preview / RecipeService::nutrition /
 * NutritionCalculator from database facts. Ids are always re-checked against the user: foods
 * must be visible to them, entries and recipes must be theirs.
 *
 * Used twice per proposal: when the model proposes, and again at confirmation (after applying
 * the user's edits), so what is executed is exactly what was last previewed.
 */
class ProposalPreviewBuilder
{
    public function __construct(
        private readonly NutritionCalculator $calculator,
        private readonly FoodLogService $log,
        private readonly RecipeService $recipes,
        private readonly DailySummaryService $summaries,
        private readonly UnitResolver $units,
        private readonly FoodPresenter $presenter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, preview: array<string, mixed>} normalised payload + preview
     *
     * @throws ProposalInvalidException
     */
    public function build(User $user, ProposalType $type, array $payload): array
    {
        return match ($type) {
            ProposalType::LogMeal => $this->logMeal($user, $payload),
            ProposalType::UpdateLogEntry => $this->updateEntry($user, $payload),
            ProposalType::DeleteLogEntry => $this->deleteEntry($user, $payload),
            ProposalType::SaveRecipe => $this->saveRecipe($user, $payload),
            ProposalType::MealPlan => $this->mealPlan($user, $payload),
        };
    }

    private function logMeal(User $user, array $payload): array
    {
        $data = $this->validate($payload, [
            'date' => ['nullable', 'string', 'max:20'],
            'meal' => ['required', Rule::enum(Meal::class)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.food_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.weighed_cooked' => ['sometimes', 'boolean'],
        ]);

        $date = $this->date($data['date'] ?? null, 'date');
        $items = array_values($data['items']);
        $foods = $this->foods($user, array_column($items, 'food_id'));

        $errors = [];
        $rows = [];
        $normalised = [];
        $parts = [];

        foreach ($items as $index => $item) {
            $food = $foods->get((int) $item['food_id']);
            if ($food === null) {
                $errors[] = $this->error("items.$index.food_id", 'food_not_accessible', __('nutrition_errors.food_not_accessible'));

                continue;
            }

            $quantity = (float) $item['quantity'];
            $unit = UnitResolver::normalize($item['unit']);
            $weighedCooked = (bool) ($item['weighed_cooked'] ?? false);

            $nutrients = $this->foodNutrition($food, $quantity, $unit, $weighedCooked, "items.$index", $errors);
            if ($nutrients === null) {
                continue;
            }

            $parts[] = $nutrients;
            $normalised[] = ['food_id' => $food->id, 'quantity' => $quantity, 'unit' => $unit, 'weighed_cooked' => $weighedCooked];
            $rows[] = $this->itemRow($food, $quantity, $unit, $nutrients) + ['weighed_cooked' => $weighedCooked];
        }

        $this->throwIf($errors);

        $totals = $this->calculator->dailyTotals($parts);
        $summary = $this->summaries->forDate($user, $date);

        return [
            'payload' => ['date' => $date->toDateString(), 'meal' => $this->mealValue($data['meal']), 'items' => $normalised],
            'preview' => [
                'date' => $date->toDateString(),
                'meal' => $this->mealValue($data['meal']),
                'item_count' => count($rows),
                'items' => $rows,
                'totals' => $this->presenter->nutrients($totals),
                'is_estimate' => $this->anyEstimate($rows),
                'day' => [
                    'consumed_before' => $this->presenter->nutrients($summary->totals),
                    'targets' => $summary->targets?->toArray(),
                    'remaining_after' => $summary->targets === null
                        ? null
                        : $this->calculator->remaining($summary->targets, $summary->totals->plus($totals))->toArray(),
                ],
            ],
        ];
    }

    private function updateEntry(User $user, array $payload): array
    {
        // the model may say "date"; the service calls it logged_on
        if (is_array($payload['changes'] ?? null) && array_key_exists('date', $payload['changes'])) {
            $payload['changes']['logged_on'] ??= $payload['changes']['date'];
            unset($payload['changes']['date']);
        }

        $data = $this->validate($payload, [
            'entry_id' => ['required', 'integer'],
            'changes' => ['required', 'array', 'min:1'],
            'changes.quantity' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:100000'],
            'changes.unit' => ['sometimes', 'required', 'string', 'max:30'],
            'changes.meal' => ['sometimes', 'required', Rule::enum(Meal::class)],
            'changes.logged_on' => ['sometimes', 'required', 'string', 'max:20'],
        ]);

        $unknown = array_diff(array_keys($payload['changes']), ['quantity', 'unit', 'meal', 'logged_on']);
        if ($unknown !== []) {
            $this->throwIf([$this->error('changes', 'unknown_fields', __('nutrition_errors.unknown_fields', ['fields' => implode(', ', $unknown)]))]);
        }

        $entry = $this->entry($user, (int) $data['entry_id']);
        $changes = [];
        $errors = [];

        if (isset($data['changes']['meal'])) {
            $changes['meal'] = $this->mealValue($data['changes']['meal']);
        }
        if (isset($data['changes']['logged_on'])) {
            $changes['logged_on'] = $this->date($data['changes']['logged_on'], 'changes.logged_on')->toDateString();
        }

        $nutrients = $entry->nutrients();
        $quantity = $entry->quantity;
        $unit = $entry->unit;

        if (isset($data['changes']['quantity']) || isset($data['changes']['unit'])) {
            $quantity = (float) ($data['changes']['quantity'] ?? $entry->quantity);
            $unit = isset($data['changes']['unit']) ? UnitResolver::normalize($data['changes']['unit']) : $entry->unit;

            if (isset($data['changes']['quantity'])) {
                $changes['quantity'] = $quantity;
            }
            if (isset($data['changes']['unit'])) {
                $changes['unit'] = $unit;
            }

            $nutrients = $this->entryNutrition($entry, $quantity, $unit, $errors);
        }

        $this->throwIf($errors);

        return [
            'payload' => ['entry_id' => $entry->id, 'changes' => $changes],
            'preview' => [
                'item_count' => 1,
                'before' => $this->presenter->entry($entry),
                'after' => [
                    'date' => $changes['logged_on'] ?? $entry->logged_on->toDateString(),
                    'meal' => $changes['meal'] ?? $entry->meal->value,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'nutrition' => $this->presenter->nutrients($nutrients),
                ],
                'is_estimate' => (bool) $entry->is_estimate,
            ],
        ];
    }

    private function deleteEntry(User $user, array $payload): array
    {
        $data = $this->validate($payload, ['entry_id' => ['required', 'integer']]);
        $entry = $this->entry($user, (int) $data['entry_id']);

        return [
            'payload' => ['entry_id' => $entry->id],
            'preview' => ['item_count' => 1, 'entry' => $this->presenter->entry($entry)],
        ];
    }

    private function saveRecipe(User $user, array $payload): array
    {
        $data = $this->validate($payload, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'servings' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'cooked_weight_g' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.food_id' => ['required', 'integer'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:30'],
        ]);

        $ingredients = array_values($data['ingredients']);
        $foods = $this->foods($user, array_column($ingredients, 'food_id'));

        $errors = [];
        $rows = [];
        $normalised = [];
        $models = new Collection;

        foreach ($ingredients as $index => $ingredient) {
            $food = $foods->get((int) $ingredient['food_id']);
            if ($food === null) {
                $errors[] = $this->error("ingredients.$index.food_id", 'food_not_accessible', __('nutrition_errors.food_not_accessible'));

                continue;
            }

            $quantity = (float) $ingredient['quantity'];
            $unit = UnitResolver::normalize($ingredient['unit'] ?? 'g');

            $nutrients = $this->foodNutrition($food, $quantity, $unit, false, "ingredients.$index", $errors);
            if ($nutrients === null) {
                continue;
            }

            $grams = $this->units->toGrams($food, $quantity, $unit);
            $normalised[] = ['food_id' => $food->id, 'quantity' => $quantity, 'unit' => $unit];
            $rows[] = $this->itemRow($food, $quantity, $unit, $nutrients);
            $models->push((new RecipeIngredient(['food_id' => $food->id, 'quantity' => $quantity, 'unit' => $unit, 'grams' => $grams]))
                ->setRelation('food', $food));
        }

        $this->throwIf($errors);

        $servings = (float) $data['servings'];
        $cookedWeight = isset($data['cooked_weight_g']) ? (float) $data['cooked_weight_g'] : null;

        // An unsaved recipe with its relations set in memory: RecipeService::nutrition computes
        // exactly what it will compute for the saved recipe, and nothing touches the database.
        $draft = (new Recipe(['servings' => $servings, 'cooked_weight_g' => $cookedWeight]))->setRelation('ingredients', $models);
        $nutrition = $this->recipes->nutrition($draft);

        return [
            'payload' => [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'servings' => $servings,
                'cooked_weight_g' => $cookedWeight,
                'ingredients' => $normalised,
            ],
            'preview' => [
                'name' => $data['name'],
                'servings' => $servings,
                'item_count' => count($rows),
                'ingredients' => $rows,
                'total' => $this->presenter->nutrients($nutrition->total),
                'per_serving' => $this->presenter->nutrients($nutrition->perServing),
                'per_100g' => $nutrition->per100g === null ? null : $this->presenter->nutrients($nutrition->per100g),
                'raw_weight_g' => $nutrition->rawWeightGrams,
                'finished_weight_g' => $nutrition->finishedWeightGrams,
                'finished_weight_is_measured' => $nutrition->finishedWeightIsMeasured,
                'is_estimate' => $this->anyEstimate($rows),
            ],
        ];
    }

    private function mealPlan(User $user, array $payload): array
    {
        $data = $this->validate($payload, [
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'string', 'max:20'],
            'days' => ['required', 'integer', 'between:1,31'],
            'items' => ['required', 'array', 'min:1', 'max:400'],
            'items.*.planned_on' => ['required', 'string', 'max:20'],
            'items.*.meal' => ['required', Rule::enum(Meal::class)],
            'items.*.food_id' => ['nullable', 'integer'],
            'items.*.recipe_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $startsOn = $this->date($data['starts_on'], 'starts_on');
        $days = (int) $data['days'];
        $endsOn = $startsOn->addDays($days - 1);
        $items = array_values($data['items']);

        $foods = $this->foods($user, array_filter(array_column($items, 'food_id')));
        $recipes = Recipe::query()->where('user_id', $user->id)
            ->whereIn('id', array_filter(array_column($items, 'recipe_id')))
            ->with('ingredients.food')->get()->keyBy('id');

        $errors = [];
        $normalised = [];
        $byDay = [];

        foreach ($items as $index => $item) {
            $foodId = $item['food_id'] ?? null;
            $recipeId = $item['recipe_id'] ?? null;

            if (($foodId === null) === ($recipeId === null)) {
                $errors[] = $this->error("items.$index.food_id", 'one_of_food_or_recipe', __('nutrition_errors.one_of_food_or_recipe'));

                continue;
            }

            $plannedOn = AiClock::parseDate($item['planned_on']);
            if ($plannedOn === null || $plannedOn->lessThan($startsOn) || $plannedOn->greaterThan($endsOn)) {
                $errors[] = $this->error("items.$index.planned_on", 'outside_plan_range', __('nutrition_errors.outside_plan_range'));

                continue;
            }

            $quantity = (float) $item['quantity'];
            $meal = $this->mealValue($item['meal']);

            if ($foodId !== null) {
                $food = $foods->get((int) $foodId);
                if ($food === null) {
                    $errors[] = $this->error("items.$index.food_id", 'food_not_accessible', __('nutrition_errors.food_not_accessible'));

                    continue;
                }
                $unit = UnitResolver::normalize($item['unit'] ?? 'g');
                $nutrients = $this->foodNutrition($food, $quantity, $unit, false, "items.$index", $errors);
                if ($nutrients === null) {
                    continue;
                }
                $row = $this->itemRow($food, $quantity, $unit, $nutrients);
            } else {
                $recipe = $recipes->get((int) $recipeId);
                if ($recipe === null) {
                    $errors[] = $this->error("items.$index.recipe_id", 'recipe_not_accessible', __('nutrition_errors.recipe_not_accessible'));

                    continue;
                }
                $unit = UnitResolver::normalize($item['unit'] ?? 'serving');
                try {
                    $nutrients = $this->log->previewRecipe($recipe, $quantity, $unit);
                } catch (UnknownUnitException|InvalidQuantityException|LoggingException $e) {
                    $errors[] = $this->error("items.$index.unit", 'not_calculable', $e->getMessage());

                    continue;
                }
                $row = [
                    'recipe_id' => $recipe->id,
                    'name' => $recipe->name,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'nutrition' => $this->presenter->nutrients($nutrients),
                    'is_estimate' => $this->recipes->isEstimate($recipe),
                ];
            }

            $day = $plannedOn->toDateString();
            $note = $item['note'] ?? null;

            $normalised[] = [
                'planned_on' => $day,
                'meal' => $meal,
                'food_id' => $foodId === null ? null : (int) $foodId,
                'recipe_id' => $recipeId === null ? null : (int) $recipeId,
                'quantity' => $quantity,
                'unit' => $unit,
                'note' => $note,
            ];
            $byDay[$day]['items'][] = $row + ['meal' => $meal, 'note' => $note];
            $byDay[$day]['parts'][] = $nutrients;
        }

        $this->throwIf($errors);
        ksort($byDay);

        $targets = $user->profile()->first()?->targets();
        $daysPreview = [];
        foreach ($byDay as $day => $bucket) {
            $totals = $this->calculator->dailyTotals($bucket['parts']);
            $daysPreview[] = [
                'date' => $day,
                'items' => $bucket['items'],
                'totals' => $this->presenter->nutrients($totals),
                'remaining_vs_targets' => $targets === null ? null : $this->calculator->remaining($targets, $totals)->toArray(),
                'is_estimate' => $this->anyEstimate($bucket['items']),
            ];
        }

        return [
            'payload' => ['name' => $data['name'], 'starts_on' => $startsOn->toDateString(), 'days' => $days, 'items' => $normalised],
            'preview' => [
                'name' => $data['name'],
                'starts_on' => $startsOn->toDateString(),
                'days' => $days,
                'item_count' => count($normalised),
                'targets' => $targets?->toArray(),
                'by_day' => $daysPreview,
            ],
        ];
    }

    // ───────────────────────────── helpers ─────────────────────────────

    /** What a food amount would be logged as, or null (and an error) when it cannot be calculated. */
    private function foodNutrition(Food $food, float $quantity, string $unit, bool $weighedCooked, string $path, array &$errors): ?Nutrients
    {
        try {
            return $this->log->preview($food, $quantity, $unit, $weighedCooked);
        } catch (UnknownUnitException $e) {
            $errors[] = $this->error("$path.unit", 'unknown_unit', $e->getMessage()) + [
                'available_units' => array_column($this->units->unitsFor($food), 'unit'),
            ];
        } catch (InvalidQuantityException $e) {
            $errors[] = $this->error("$path.quantity", 'invalid_quantity', $e->getMessage());
        } catch (LoggingException $e) {
            $errors[] = $this->error("$path.weighed_cooked", $e->reason, $e->getMessage());
        }

        return null;
    }

    /** Mirrors FoodLogService::update(): current facts of the food / recipe, or a scaled snapshot when it was deleted. */
    private function entryNutrition(FoodLogEntry $entry, float $quantity, string $unit, array &$errors): Nutrients
    {
        try {
            if ($entry->food_id !== null && $entry->food !== null) {
                $entry->food->loadMissing('units');

                return $this->log->preview($entry->food, $quantity, $unit, (bool) $entry->weighed_cooked);
            }
            if ($entry->recipe_id !== null && $entry->recipe !== null) {
                return $this->log->previewRecipe($entry->recipe, $quantity, $unit);
            }
            if ($unit !== $entry->unit || $entry->quantity <= 0) {
                throw LoggingException::sourceDeleted();
            }

            return $entry->nutrients()->scale($quantity / $entry->quantity);
        } catch (UnknownUnitException $e) {
            $errors[] = $this->error('changes.unit', 'unknown_unit', $e->getMessage());
        } catch (InvalidQuantityException $e) {
            $errors[] = $this->error('changes.quantity', 'invalid_quantity', $e->getMessage());
        } catch (LoggingException $e) {
            $errors[] = $this->error('changes', $e->reason, $e->getMessage());
        }

        return $entry->nutrients();
    }

    /** @return array<string, mixed> */
    private function itemRow(Food $food, float $quantity, string $unit, Nutrients $nutrients): array
    {
        return [
            'food_id' => $food->id,
            'name_ar' => $food->name_ar,
            'name_en' => $food->name_en,
            'quantity' => $quantity,
            'unit' => $unit,
            'grams_entered' => $this->units->toGrams($food, $quantity, $unit),
            'nutrition' => $this->presenter->nutrients($nutrients),
            'is_estimate' => (bool) $food->is_estimate,
            'confidence' => $food->confidence?->value,
            'source' => $food->source,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function anyEstimate(array $rows): bool
    {
        return in_array(true, array_column($rows, 'is_estimate'), true);
    }

    /** @return Collection<int, Food> visible to the user, keyed by id */
    private function foods(User $user, array $ids): Collection
    {
        return Food::query()->visibleTo($user)->with('units')
            ->whereIn('id', array_map('intval', $ids))
            ->get()->keyBy('id');
    }

    /** The user's own entry – another user's id looks exactly like a missing one. */
    private function entry(User $user, int $id): FoodLogEntry
    {
        $entry = FoodLogEntry::query()->where('user_id', $user->id)->with(['food.units', 'recipe'])->find($id);

        if ($entry === null) {
            $this->throwIf([$this->error('entry_id', 'entry_not_found', __('coach.proposals.errors.entry_not_found'))]);
        }

        return $entry;
    }

    private function date(mixed $value, string $field): CarbonImmutable
    {
        $date = AiClock::parseDate($value);
        if ($date === null) {
            $this->throwIf([$this->error($field, 'invalid_date', __('coach.proposals.errors.invalid_date'))]);
        }

        return $date;
    }

    private function mealValue(mixed $meal): string
    {
        return $meal instanceof Meal ? $meal->value : Meal::from((string) $meal)->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $payload, array $rules): array
    {
        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = $this->error($field, 'invalid', $message);
                }
            }
            $this->throwIf($errors);
        }

        return $validator->validated();
    }

    /** @return array{field: string, code: string, message: string} */
    private function error(string $field, string $code, string $message): array
    {
        return ['field' => $field, 'code' => $code, 'message' => $message];
    }

    private function throwIf(array $errors): void
    {
        if ($errors !== []) {
            throw new ProposalInvalidException(array_values($errors));
        }
    }
}
