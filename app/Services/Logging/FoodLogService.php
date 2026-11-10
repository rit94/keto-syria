<?php

namespace App\Services\Logging;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Enums\FoodState;
use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Recipes\RecipeService;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Writes the food diary. Every number stored here is produced by NutritionCalculator from
 * `Food::per100g()` (or a recipe's ingredients) and frozen as a snapshot on the entry.
 *
 * What the columns of an entry mean:
 *  - `quantity` + `unit`: exactly what the user entered (e.g. 2 'piece', or 140 'g').
 *  - `grams`: the resolved weight the calculation was made with. For a food weighed after cooking
 *    (`weighed_cooked` = true, raw food with a `cooked_yield`) this is the RAW-EQUIVALENT weight,
 *    not the figure the user typed – 140 g cooked at a yield of 0.70 is stored as grams = 200.
 *    Null only for recipes logged by serving.
 *  - `description`: display-name snapshot in the user's locale at log time.
 *  - `is_estimate`: copied from the food (recipes: true if any ingredient food is an estimate).
 */
class FoodLogService
{
    private const EDITABLE = ['quantity', 'unit', 'meal', 'logged_on'];

    public function __construct(
        private readonly NutritionCalculator $calculator,
        private readonly UnitResolver $units,
        private readonly RecipeService $recipes,
    ) {}

    /**
     * @throws AuthorizationException food is another user's custom food
     * @throws UnknownUnitException|InvalidQuantityException
     * @throws LoggingException cooked weight given for a raw food without a cooked_yield
     */
    public function logFood(
        User $user,
        Food $food,
        float $quantity,
        string $unit,
        Meal $meal,
        CarbonInterface $date,
        LogSource $source = LogSource::Manual,
        ?string $groupId = null,
        bool $weighedCooked = false,
    ): FoodLogEntry {
        $this->assertFoodVisible($user, $food);

        [$grams, $nutrients] = $this->resolveFood($food, $quantity, $unit, $weighedCooked);

        return FoodLogEntry::create([
            'user_id' => $user->id,
            'logged_on' => $date->toDateString(),
            'meal' => $meal,
            'food_id' => $food->id,
            'description' => $food->displayName($this->localeOf($user)),
            'quantity' => $quantity,
            'unit' => UnitResolver::normalize($unit),
            'grams' => $grams,
            'weighed_cooked' => $weighedCooked,
            ...FoodLogEntry::snapshotColumns($nutrients),
            'is_estimate' => (bool) $food->is_estimate,
            'source' => $source,
            'group_id' => $groupId,
        ]);
    }

    /**
     * $unit 'serving' → quantity × per-serving nutrition; 'g' → quantity grams of the finished dish
     * (measured cooked weight when the recipe has one, otherwise the raw ingredient weight).
     *
     * @throws AuthorizationException recipe is not the user's
     * @throws UnknownUnitException unit other than 'serving' / 'g'
     * @throws InvalidQuantityException
     * @throws LoggingException logged by grams but the recipe has no weight at all
     */
    public function logRecipe(
        User $user,
        Recipe $recipe,
        float $quantity,
        string $unit,
        Meal $meal,
        CarbonInterface $date,
        LogSource $source = LogSource::Manual,
    ): FoodLogEntry {
        $this->assertRecipeOwned($user, $recipe);

        [$grams, $nutrients] = $this->resolveRecipe($recipe, $quantity, $unit);

        return FoodLogEntry::create([
            'user_id' => $user->id,
            'logged_on' => $date->toDateString(),
            'meal' => $meal,
            'recipe_id' => $recipe->id,
            'description' => $recipe->name,
            'quantity' => $quantity,
            'unit' => UnitResolver::normalize($unit),
            'grams' => $grams,
            'weighed_cooked' => false,
            ...FoodLogEntry::snapshotColumns($nutrients),
            'is_estimate' => $this->recipes->isEstimate($recipe),
            'source' => $source,
        ]);
    }

    /**
     * Log several foods as one meal: one transaction, one shared group_id, all or nothing.
     *
     * @param  array<int, array{food_id: int, quantity: float, unit: string, weighed_cooked?: bool}>  $items
     * @return Collection<int, FoodLogEntry>
     *
     * @throws ValidationException malformed items
     * @throws AuthorizationException a food that does not exist or is not visible to the user
     * @throws UnknownUnitException|InvalidQuantityException|LoggingException
     */
    public function logMany(User $user, array $items, Meal $meal, CarbonInterface $date, LogSource $source): Collection
    {
        $validated = Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.food_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.weighed_cooked' => ['sometimes', 'boolean'],
        ])->validate()['items'];

        $foods = Food::query()->visibleTo($user)->with('units')
            ->whereIn('id', array_column($validated, 'food_id'))
            ->get()->keyBy('id');

        $groupId = (string) Str::uuid();

        return DB::transaction(function () use ($user, $validated, $foods, $meal, $date, $source, $groupId) {
            $entries = new Collection;

            foreach ($validated as $item) {
                $food = $foods->get((int) $item['food_id'])
                    ?? throw new AuthorizationException(__('nutrition_errors.food_not_accessible'));

                $entries->push($this->logFood(
                    $user, $food, (float) $item['quantity'], $item['unit'], $meal, $date, $source, $groupId,
                    (bool) ($item['weighed_cooked'] ?? false),
                ));
            }

            return $entries;
        });
    }

    /**
     * Change quantity, unit, meal and/or logged_on – nothing else.
     *
     * When quantity or unit is part of $changes the grams are re-resolved and the snapshot is
     * recalculated by the engine from the CURRENT facts of the food / recipe (so a corrected food
     * also corrects the entry). Changing only meal / logged_on leaves the snapshot untouched.
     *
     * If the food / recipe has since been deleted there are no facts left: with the unit unchanged
     * the stored snapshot is scaled by new ÷ old quantity; a unit change is refused.
     *
     * The caller authorises (FoodLogEntryPolicy::update) before calling.
     *
     * @throws ValidationException unknown keys or invalid values
     * @throws UnknownUnitException|InvalidQuantityException
     * @throws LoggingException source deleted and unit changed, or cooked yield no longer available
     */
    public function update(FoodLogEntry $entry, array $changes): FoodLogEntry
    {
        $unknown = array_diff(array_keys($changes), self::EDITABLE);
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                array_map('strval', $unknown),
                __('nutrition_errors.unknown_fields', ['fields' => implode(', ', $unknown)]),
            ));
        }

        $data = Validator::make($changes, [
            'quantity' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'meal' => ['sometimes', 'required', Rule::enum(Meal::class)],
            'logged_on' => ['sometimes', 'required', 'date'],
        ])->validate();

        $attributes = [];

        if (array_key_exists('meal', $data)) {
            $attributes['meal'] = $data['meal'] instanceof Meal ? $data['meal'] : Meal::from($data['meal']);
        }
        if (array_key_exists('logged_on', $data)) {
            $attributes['logged_on'] = Carbon::parse($data['logged_on'])->toDateString();
        }
        if (array_key_exists('quantity', $data) || array_key_exists('unit', $data)) {
            $quantity = (float) ($data['quantity'] ?? $entry->quantity);
            $unit = array_key_exists('unit', $data) ? UnitResolver::normalize($data['unit']) : $entry->unit;

            $attributes += $this->recalculate($entry, $quantity, $unit);
        }

        $entry->update($attributes);

        return $entry;
    }

    /** The caller authorises (FoodLogEntryPolicy::delete) before calling. */
    public function delete(FoodLogEntry $entry): void
    {
        $entry->delete();
    }

    /** What logFood() would store, without writing anything. */
    public function preview(Food $food, float $quantity, string $unit, bool $weighedCooked = false): Nutrients
    {
        return $this->resolveFood($food, $quantity, $unit, $weighedCooked)[1];
    }

    /** What logRecipe() would store, without writing anything. */
    public function previewRecipe(Recipe $recipe, float $quantity, string $unit): Nutrients
    {
        return $this->resolveRecipe($recipe, $quantity, $unit)[1];
    }

    /** @return array<string, mixed> column values for the edited amount */
    private function recalculate(FoodLogEntry $entry, float $quantity, string $unit): array
    {
        $food = $entry->food_id !== null ? $entry->food : null;
        $recipe = $entry->recipe_id !== null ? $entry->recipe : null;

        if ($food !== null) {
            [$grams, $nutrients] = $this->resolveFood($food, $quantity, $unit, (bool) $entry->weighed_cooked);
            $isEstimate = (bool) $food->is_estimate;
        } elseif ($recipe !== null) {
            [$grams, $nutrients] = $this->resolveRecipe($recipe, $quantity, $unit);
            $isEstimate = $this->recipes->isEstimate($recipe);
        } else {
            InvalidQuantityException::assertValid($quantity);
            if ($unit !== $entry->unit || $entry->quantity <= 0) {
                throw LoggingException::sourceDeleted();
            }

            $factor = $quantity / $entry->quantity;
            $nutrients = $entry->nutrients()->scale($factor);
            $grams = $entry->grams === null ? null : $entry->grams * $factor;
            $isEstimate = (bool) $entry->is_estimate;
        }

        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'grams' => $grams,
            ...FoodLogEntry::snapshotColumns($nutrients),
            'is_estimate' => $isEstimate,
        ];
    }

    /** @return array{0: float, 1: Nutrients} [grams used for the calculation, nutrients] */
    private function resolveFood(Food $food, float $quantity, string $unit, bool $weighedCooked): array
    {
        $grams = $this->units->toGrams($food, $quantity, $unit);

        // Facts of a raw food describe the raw weight. A cooked / ready-to-eat food already
        // describes the weight as eaten, so there is nothing to convert.
        if ($weighedCooked && $food->state === FoodState::Raw) {
            if ($food->cooked_yield === null) {
                throw LoggingException::noCookedYield();
            }
            $grams = $this->calculator->rawGramsFromCooked($grams, $food->cooked_yield);
        }

        return [$grams, $this->calculator->forGrams($food->per100g(), $grams)];
    }

    /** @return array{0: ?float, 1: Nutrients} [grams (null when logged by serving), nutrients] */
    private function resolveRecipe(Recipe $recipe, float $quantity, string $unit): array
    {
        InvalidQuantityException::assertValid($quantity);

        $nutrition = $this->recipes->nutrition($recipe);

        switch (UnitResolver::normalize($unit)) {
            case 'serving':
                return [null, $this->calculator->forServings($nutrition->perServing, $quantity)];
            case 'g':
                if ($nutrition->per100g === null) {
                    throw LoggingException::recipeHasNoWeight();
                }

                return [$quantity, $this->calculator->forGrams($nutrition->per100g, $quantity)];
            default:
                throw new UnknownUnitException($unit);
        }
    }

    private function assertFoodVisible(User $user, Food $food): void
    {
        if ($food->user_id !== null && (int) $food->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('nutrition_errors.food_not_accessible'));
        }
    }

    private function assertRecipeOwned(User $user, Recipe $recipe): void
    {
        if ((int) $recipe->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('nutrition_errors.recipe_not_accessible'));
        }
    }

    private function localeOf(User $user): string
    {
        return $user->locale ?: config('app.locale', 'ar');
    }
}
