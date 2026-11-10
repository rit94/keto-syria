<?php

namespace App\Services\Food;

use App\Domain\Nutrition\Nutrients;
use App\Enums\CarbBasis;
use App\Enums\Confidence;
use App\Enums\FoodCategory;
use App\Enums\FoodState;
use App\Enums\Preparation;
use App\Models\Food;
use App\Models\User;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates and maintains a user's private ingredients (foods.user_id = the owner).
 *
 * Accepted $data keys:
 *   name_ar, name_en            at least one is required
 *   calories, protein_g, carbs_g, fat_g      required, as printed on the label
 *   fiber_g, saturated_fat_g    optional – omitted / null means "unknown", never zero
 *   label_basis                 'total' (default) or 'net': whether carbs_g includes fiber.
 *                               'net' values are converted to total carbs with Nutrients::fromLabel()
 *   per_grams                   amount the label values refer to (default 100)
 *   is_estimate                 true → source "User estimate", false (default) → "User label"
 *   category, state, preparation, cooked_yield, is_keto_friendly, notes_ar, notes_en
 *   aliases                     list of extra names to search by
 *   units                       list of ['unit' => key, 'label_ar' => …, 'label_en' => …, 'grams' => …, 'is_default' => bool]
 *
 * Hard problems throw Illuminate\Validation\ValidationException. Soft problems (calories
 * that disagree with the macros) are reported through warnings() so the UI can ask the
 * user to double-check without blocking the save.
 */
class CustomFoodService
{
    public const SOURCE_LABEL = 'User label';

    public const SOURCE_ESTIMATE = 'User estimate';

    /** Calories may differ from Atwater 4/4/9 by this fraction before a warning is raised. */
    public const CALORIE_TOLERANCE = 0.20;

    /** Unit keys that UnitResolver handles globally and a food may not redefine. */
    private const RESERVED_UNITS = ['g', 'kg', 'oz', 'ouqiya', 'serving'];

    private const NUTRIENT_KEYS = ['calories', 'protein_g', 'carbs_g', 'fiber_g', 'fat_g', 'saturated_fat_g'];

    /** @var list<array{code: string, message: string, calories: float, expected: float}> */
    private array $warnings = [];

    public function __construct(private readonly FoodIndexer $indexer) {}

    public function create(User $user, array $data): Food
    {
        $prepared = $this->prepare($this->plain($data));
        $this->warnings = $prepared['warnings'];

        return DB::transaction(function () use ($user, $prepared) {
            $food = new Food;
            $food->forceFill([...$prepared['attributes'], 'user_id' => $user->id, 'slug' => null, 'parent_food_id' => null])->save();

            $this->indexer->sync($food, $prepared['aliases'] ?? [], isNew: true);
            $this->indexer->syncUnits($food, $prepared['units'] ?? []);

            return $this->fresh($food);
        });
    }

    public function update(Food $food, array $data): Food
    {
        $this->assertCustom($food);

        $data = $this->plain($data);
        $prepared = $this->prepare($this->mergeWithExisting($food, $data));
        $this->warnings = $prepared['warnings'];

        // The stored basis only changes when the carbohydrate figure itself was re-entered.
        if (! array_key_exists('carbs_g', $data)) {
            $prepared['attributes']['label_basis'] = $food->label_basis?->value ?? CarbBasis::Total->value;
        }

        return DB::transaction(function () use ($food, $data, $prepared) {
            $food->forceFill($prepared['attributes'])->save();

            $aliases = array_key_exists('aliases', $data)
                ? ($prepared['aliases'] ?? [])
                : $food->aliases()->orderBy('id')->pluck('alias')->all();
            $this->indexer->sync($food, $aliases);

            if (array_key_exists('units', $data)) {
                $this->indexer->syncUnits($food, $prepared['units'] ?? []);
            }

            return $this->fresh($food);
        });
    }

    public function delete(Food $food): void
    {
        $this->assertCustom($food);

        $recipes = DB::table('recipe_ingredients')->where('food_id', $food->id)->distinct()->count('recipe_id');
        if ($recipes > 0) {
            throw FoodInUseException::byRecipes($recipes);
        }

        // Log entries keep their own nutrient snapshot; their food_id is nulled by the FK.
        $food->delete();
    }

    /**
     * Validate without saving and return the soft warnings the data would raise.
     *
     * @return list<array{code: string, message: string, calories: float, expected: float}>
     */
    public function check(array $data): array
    {
        return $this->prepare($this->plain($data))['warnings'];
    }

    /**
     * Soft warnings raised by the most recent create() / update() call.
     *
     * @return list<array{code: string, message: string, calories: float, expected: float}>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** Reload the food with its aliases and units in the order they were entered. */
    private function fresh(Food $food): Food
    {
        return $food->refresh()->load([
            'units' => fn ($query) => $query->orderBy('id'),
            'aliases' => fn ($query) => $query->orderBy('id'),
        ]);
    }

    private function assertCustom(Food $food): void
    {
        if (! $food->exists || ! $food->isCustom()) {
            throw new AuthorizationException(__('custom_food.errors.catalogue_readonly'));
        }
    }

    /** Enum instances → their scalar values, so UI code may pass either. */
    private function plain(array $data): array
    {
        return array_map(static fn ($value) => $value instanceof BackedEnum ? $value->value : $value, $data);
    }

    /** Current values of the food overlaid with the changes, expressed like fresh label input. */
    private function mergeWithExisting(Food $food, array $data): array
    {
        $perGrams = isset($data['per_grams']) && is_numeric($data['per_grams']) ? (float) $data['per_grams'] : 100.0;

        if (abs($perGrams - 100.0) > 1e-9) {
            // A different reference amount only makes sense for a complete set of label values.
            $missing = array_diff(['calories', 'protein_g', 'carbs_g', 'fat_g'], array_keys($data));
            if ($missing !== []) {
                throw ValidationException::withMessages(['per_grams' => __('custom_food.errors.per_grams_partial')]);
            }

            $current = [];
        } else {
            $current = [
                'calories' => $food->calories,
                'protein_g' => $food->protein_g,
                'carbs_g' => $food->carbs_g,
                'fiber_g' => $food->fiber_g,
                'fat_g' => $food->fat_g,
                'saturated_fat_g' => $food->saturated_fat_g,
            ];
        }

        $merged = [
            'name_ar' => $food->name_ar,
            'name_en' => $food->name_en,
            'category' => $food->category?->value,
            'state' => $food->state?->value,
            'preparation' => $food->preparation?->value,
            'cooked_yield' => $food->cooked_yield,
            'is_estimate' => $food->is_estimate,
            'is_keto_friendly' => $food->is_keto_friendly,
            'notes_ar' => $food->notes_ar,
            'notes_en' => $food->notes_en,
            ...$current,
            ...$data,
        ];

        // Stored carbs are always total; a basis only applies to a re-entered carb figure.
        if (! array_key_exists('carbs_g', $data)) {
            $merged['label_basis'] = CarbBasis::Total->value;
        }

        return $merged;
    }

    /**
     * @return array{attributes: array<string, mixed>, aliases: ?list<string>, units: ?list<array<string, mixed>>, warnings: list<array<string, mixed>>}
     */
    private function prepare(array $data): array
    {
        foreach (['name_ar', 'name_en'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }

        $v = Validator::make($data, $this->rules(), [], $this->attributeNames())->validate();

        $basis = $v['label_basis'] ?? CarbBasis::Total->value;
        $perGrams = (float) ($v['per_grams'] ?? 100);
        $number = static fn (string $key): ?float => isset($v[$key]) && $v[$key] !== '' ? (float) $v[$key] : null;

        // The engine's own value object does the net → total conversion and the per-100 g scaling.
        $per100 = Nutrients::fromLabel(
            calories: (float) $v['calories'],
            protein: (float) $v['protein_g'],
            carbs: (float) $v['carbs_g'],
            fiber: $number('fiber_g'),
            fat: (float) $v['fat_g'],
            saturatedFat: $number('saturated_fat_g'),
            carbBasis: $basis,
        )->scale(100 / $perGrams);

        $this->assertPlausible($per100);

        $nameAr = ($v['name_ar'] ?? '') !== '' ? $v['name_ar'] : $v['name_en'];
        $nameEn = ($v['name_en'] ?? '') !== '' ? $v['name_en'] : null;
        $isEstimate = (bool) ($v['is_estimate'] ?? false);

        $units = null;
        if (array_key_exists('units', $v) && $v['units'] !== null) {
            $units = array_map(static fn (array $unit): array => [
                'unit' => $unit['unit'],
                'label_ar' => ($unit['label_ar'] ?? '') !== '' ? $unit['label_ar'] : $unit['label_en'],
                'label_en' => ($unit['label_en'] ?? '') !== '' ? $unit['label_en'] : $unit['label_ar'],
                'grams' => (float) $unit['grams'],
                'is_default' => (bool) ($unit['is_default'] ?? false),
            ], array_values($v['units']));
        }

        return [
            'attributes' => [
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'category' => $v['category'] ?? FoodCategory::Other->value,
                'state' => $v['state'] ?? FoodState::NotApplicable->value,
                'preparation' => $v['preparation'] ?? null,
                'calories' => $per100->calories,
                'protein_g' => $per100->protein,
                'carbs_g' => $per100->carbs,
                'fiber_g' => $per100->fiber,
                'fat_g' => $per100->fat,
                'saturated_fat_g' => $per100->saturatedFat,
                'label_basis' => $basis,
                'cooked_yield' => $number('cooked_yield'),
                'source' => $isEstimate ? self::SOURCE_ESTIMATE : self::SOURCE_LABEL,
                'source_ref' => null,
                'confidence' => ($isEstimate ? Confidence::Low : Confidence::High)->value,
                'is_estimate' => $isEstimate,
                'notes_ar' => $v['notes_ar'] ?? null,
                'notes_en' => $v['notes_en'] ?? null,
                'is_keto_friendly' => (bool) ($v['is_keto_friendly'] ?? true),
            ],
            'aliases' => array_key_exists('aliases', $v) && $v['aliases'] !== null ? array_values($v['aliases']) : null,
            'units' => $units,
            'warnings' => $this->warningsFor($per100),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name_ar' => ['required_without:name_en', 'nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(FoodCategory::class)],
            'state' => ['nullable', Rule::enum(FoodState::class)],
            'preparation' => ['nullable', Rule::enum(Preparation::class)],
            'calories' => ['required', 'numeric', 'min:0'],
            'protein_g' => ['required', 'numeric', 'min:0'],
            'carbs_g' => ['required', 'numeric', 'min:0'],
            'fiber_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['required', 'numeric', 'min:0'],
            'saturated_fat_g' => ['nullable', 'numeric', 'min:0'],
            'label_basis' => ['nullable', Rule::enum(CarbBasis::class)],
            'per_grams' => ['nullable', 'numeric', 'gt:0', 'max:5000'],
            'cooked_yield' => ['nullable', 'numeric', 'between:0.1,5'],
            'is_estimate' => ['nullable', 'boolean'],
            'is_keto_friendly' => ['nullable', 'boolean'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'notes_en' => ['nullable', 'string', 'max:2000'],
            'aliases' => ['nullable', 'array', 'max:20'],
            'aliases.*' => ['string', 'max:100'],
            'units' => ['nullable', 'array', 'max:12'],
            'units.*.unit' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct', Rule::notIn(self::RESERVED_UNITS)],
            'units.*.label_ar' => ['required_without:units.*.label_en', 'nullable', 'string', 'max:60'],
            'units.*.label_en' => ['nullable', 'string', 'max:60'],
            'units.*.grams' => ['required', 'numeric', 'gt:0', 'max:5000'],
            'units.*.is_default' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        $names = [];
        foreach (array_keys($this->rules()) as $key) {
            $names[$key] = __('custom_food.attributes.'.str_replace(['units.*.', 'aliases.*'], ['unit_', 'alias'], $key));
        }

        return $names;
    }

    /** Checks that only make sense on the per-100 g, total-carb figures. */
    private function assertPlausible(Nutrients $per100): void
    {
        $errors = [];
        $epsilon = 0.0005;

        foreach (['protein_g' => $per100->protein, 'carbs_g' => $per100->carbs, 'fat_g' => $per100->fat] as $key => $value) {
            if ($value > 100 + $epsilon) {
                $errors[$key][] = __('custom_food.errors.over_100', ['attribute' => __('custom_food.attributes.'.$key)]);
            }
        }
        if ($per100->calories > 910) {
            $errors['calories'][] = __('custom_food.errors.calories_too_high');
        }
        if ($per100->fiber !== null && $per100->fiber > $per100->carbs + $epsilon) {
            $errors['fiber_g'][] = __('custom_food.errors.fiber_exceeds_carbs');
        }
        if ($per100->saturatedFat !== null && $per100->saturatedFat > $per100->fat + $epsilon) {
            $errors['saturated_fat_g'][] = __('custom_food.errors.saturated_exceeds_fat');
        }
        if ($per100->protein + $per100->carbs + $per100->fat > 100 + $epsilon) {
            $errors['macros'][] = __('custom_food.errors.macros_exceed_100');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return list<array{code: string, message: string, calories: float, expected: float}> */
    private function warningsFor(Nutrients $per100): array
    {
        $expected = $per100->atwaterCalories();
        $difference = abs($per100->calories - $expected);

        if ($difference <= self::CALORIE_TOLERANCE * $expected || $difference <= 5.0) {
            return [];
        }

        return [[
            'code' => 'calories_mismatch',
            'message' => __('custom_food.warnings.calories_mismatch', [
                'calories' => (int) round($per100->calories),
                'expected' => (int) round($expected),
            ]),
            'calories' => $per100->calories,
            'expected' => $expected,
        ]];
    }
}
