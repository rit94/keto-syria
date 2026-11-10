<?php

namespace App\Services\Profile;

use App\Domain\Macros\MacroCalculator;
use App\Domain\Macros\MacroInput;
use App\Domain\Macros\MacroResult;
use App\Domain\Nutrition\MacroTargets;
use App\Enums\ActivityLevel;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owns the user's body data and daily targets. All target numbers come from
 * MacroCalculator (or from the user's own validated custom targets) – never from here.
 */
class ProfileService
{
    public const int MIN_AGE = 13;

    public const int MAX_AGE = 100;

    public const float MIN_HEIGHT_CM = 120.0;

    public const float MAX_HEIGHT_CM = 230.0;

    public const float MIN_WEIGHT_KG = 30.0;

    public const float MAX_WEIGHT_KG = 300.0;

    /** Custom macro grams must add up to the custom calorie target within this share. */
    public const float CUSTOM_CALORIE_TOLERANCE = 0.10;

    public const float MAX_CUSTOM_CALORIES = 10000.0;

    public const float MAX_CUSTOM_GRAMS = 1000.0;

    public function __construct(private readonly MacroCalculator $calculator) {}

    /**
     * Validate body data and return the calculated result without saving anything.
     *
     * @param  array<string, mixed>  $data  sex, birth_date, height_cm, weight_kg, goal_weight_kg?, activity_level, goal, approach
     *
     * @throws ValidationException
     */
    public function preview(array $data): MacroResult
    {
        return $this->calculator->calculate($this->toInput($this->validateBody($data)));
    }

    /**
     * Create or update the user's profile.
     *
     * Without $customTargets the calculated, rounded targets are stored (and any earlier
     * customisation is replaced). With $customTargets they are validated against the
     * person's safety floor and stored as given with targets_customized = true.
     *
     * @param  array<string, mixed>  $data  as preview(), plus optional needs_medical_supervision (bool)
     * @param  array<string, mixed>|null  $customTargets  calories, protein_g, fat_g, net_carbs_g, carbs_g
     *
     * @throws ValidationException
     */
    public function save(User $user, array $data, ?array $customTargets = null): Profile
    {
        $body = $this->validateBody($data);
        $result = $this->calculator->calculate($this->toInput($body));

        $targets = $customTargets === null
            ? $result->roundedTargets()
            : $this->validateCustomTargets($customTargets, $result);

        $profile = Profile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'sex' => $body['sex'],
                'birth_date' => $body['birth_date'],
                'height_cm' => $body['height_cm'],
                'weight_kg' => $body['weight_kg'],
                'goal_weight_kg' => $body['goal_weight_kg'],
                'activity_level' => $body['activity_level'],
                'goal' => $body['goal'],
                'approach' => $body['approach'],
                'needs_medical_supervision' => $body['needs_medical_supervision'] || $result->requiresMedicalAdvice,
                'targets_customized' => $customTargets !== null,
                ...$this->targetColumns($targets),
            ],
        );

        $user->setRelation('profile', $profile);

        return $profile;
    }

    /**
     * Re-run the calculator for the stored body data (e.g. after a new weigh-in).
     *
     * Customised targets are left untouched. The medical-supervision flag is only ever
     * raised here, never cleared: it may have been self-declared by the user.
     */
    public function recalculate(Profile $profile): Profile
    {
        $result = $this->calculator->calculate($this->inputFromProfile($profile));

        if ($result->requiresMedicalAdvice) {
            $profile->needs_medical_supervision = true;
        }

        if (! $profile->targets_customized) {
            $profile->fill($this->targetColumns($result->roundedTargets()));
        }

        $profile->save();

        return $profile;
    }

    /** The calculator's view of a stored profile – handy for showing BMR/TDEE/warnings next to saved targets. */
    public function resultFor(Profile $profile): MacroResult
    {
        return $this->calculator->calculate($this->inputFromProfile($profile));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{sex: string, birth_date: string, height_cm: float, weight_kg: float, goal_weight_kg: ?float, activity_level: string, goal: string, approach: string, needs_medical_supervision: bool}
     *
     * @throws ValidationException
     */
    private function validateBody(array $data): array
    {
        $today = Carbon::today();
        $weightRange = ['min' => self::MIN_WEIGHT_KG, 'max' => self::MAX_WEIGHT_KG];
        $ageRange = ['min' => self::MIN_AGE, 'max' => self::MAX_AGE];

        $validated = Validator::make(
            $data,
            [
                'sex' => ['required', Rule::enum(Sex::class)],
                'birth_date' => [
                    'required',
                    'date',
                    // At least MIN_AGE years old today …
                    'before_or_equal:'.$today->copy()->subYears(self::MIN_AGE)->toDateString(),
                    // … and not yet MAX_AGE + 1.
                    'after:'.$today->copy()->subYears(self::MAX_AGE + 1)->toDateString(),
                ],
                'height_cm' => ['required', 'numeric', 'min:'.self::MIN_HEIGHT_CM, 'max:'.self::MAX_HEIGHT_CM],
                'weight_kg' => ['required', 'numeric', 'min:'.self::MIN_WEIGHT_KG, 'max:'.self::MAX_WEIGHT_KG],
                'goal_weight_kg' => ['nullable', 'numeric', 'min:'.self::MIN_WEIGHT_KG, 'max:'.self::MAX_WEIGHT_KG],
                'activity_level' => ['required', Rule::enum(ActivityLevel::class)],
                'goal' => ['required', Rule::enum(WeightGoal::class)],
                'approach' => ['required', Rule::enum(DietApproach::class)],
                'needs_medical_supervision' => ['sometimes', 'boolean'],
            ],
            [
                'birth_date.before_or_equal' => __('macros.validation.age_range', $ageRange),
                'birth_date.after' => __('macros.validation.age_range', $ageRange),
                'height_cm.min' => __('macros.validation.height_range', ['min' => self::MIN_HEIGHT_CM, 'max' => self::MAX_HEIGHT_CM]),
                'height_cm.max' => __('macros.validation.height_range', ['min' => self::MIN_HEIGHT_CM, 'max' => self::MAX_HEIGHT_CM]),
                'weight_kg.min' => __('macros.validation.weight_range', $weightRange),
                'weight_kg.max' => __('macros.validation.weight_range', $weightRange),
                'goal_weight_kg.min' => __('macros.validation.goal_weight_range', $weightRange),
                'goal_weight_kg.max' => __('macros.validation.goal_weight_range', $weightRange),
            ],
            $this->attributeNames(),
        )->validate();

        $goalWeight = $validated['goal_weight_kg'] ?? null;

        // Body measurements are stored with one decimal; calculate from exactly what is stored
        // so that save() and a later recalculate() agree.
        return [
            'sex' => $this->enumValue($validated['sex']),
            'birth_date' => Carbon::parse($validated['birth_date'])->toDateString(),
            'height_cm' => round((float) $validated['height_cm'], 1),
            'weight_kg' => round((float) $validated['weight_kg'], 1),
            'goal_weight_kg' => ($goalWeight === null || $goalWeight === '') ? null : round((float) $goalWeight, 1),
            'activity_level' => $this->enumValue($validated['activity_level']),
            'goal' => $this->enumValue($validated['goal']),
            'approach' => $this->enumValue($validated['approach']),
            'needs_medical_supervision' => filter_var($validated['needs_medical_supervision'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  array<string, mixed>  $customTargets
     *
     * @throws ValidationException
     */
    private function validateCustomTargets(array $customTargets, MacroResult $result): MacroTargets
    {
        $minCalories = (int) ceil($result->calorieFloor);
        $grams = ['required', 'numeric', 'min:0', 'max:'.self::MAX_CUSTOM_GRAMS];

        $validator = Validator::make(
            $customTargets,
            [
                'calories' => ['required', 'numeric', 'min:'.$minCalories, 'max:'.self::MAX_CUSTOM_CALORIES],
                'protein_g' => $grams,
                'fat_g' => $grams,
                'net_carbs_g' => $grams,
                'carbs_g' => [...$grams, 'gte:net_carbs_g'],
            ],
            [
                'calories.min' => __('macros.validation.calories_below_floor', ['min' => $minCalories]),
                'carbs_g.gte' => __('macros.validation.carbs_below_net'),
            ],
            $this->attributeNames(),
        );

        $validator->after(function ($validator) use ($customTargets) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $calories = (float) $customTargets['calories'];
            $macroCalories = (float) $customTargets['protein_g'] * MacroCalculator::KCAL_PER_G_PROTEIN
                + (float) $customTargets['net_carbs_g'] * MacroCalculator::KCAL_PER_G_CARBS
                + (float) $customTargets['fat_g'] * MacroCalculator::KCAL_PER_G_FAT;

            if (abs($macroCalories - $calories) > $calories * self::CUSTOM_CALORIE_TOLERANCE) {
                $validator->errors()->add('calories', __('macros.validation.macro_calorie_mismatch', [
                    'macro_calories' => (int) round($macroCalories),
                    'calories' => (int) round($calories),
                    'percent' => (int) round(self::CUSTOM_CALORIE_TOLERANCE * 100),
                ]));
            }
        });

        $validated = $validator->validate();

        return new MacroTargets(
            calories: round((float) $validated['calories'], 1),
            protein: round((float) $validated['protein_g'], 1),
            fat: round((float) $validated['fat_g'], 1),
            carbs: round((float) $validated['carbs_g'], 1),
            netCarbs: round((float) $validated['net_carbs_g'], 1),
        );
    }

    /** @param array{sex: string, birth_date: string, height_cm: float, weight_kg: float, goal_weight_kg: ?float, activity_level: string, goal: string, approach: string} $body */
    private function toInput(array $body): MacroInput
    {
        return new MacroInput(
            sex: Sex::from($body['sex']),
            ageYears: $this->ageOn(Carbon::parse($body['birth_date'])),
            heightCm: $body['height_cm'],
            weightKg: $body['weight_kg'],
            goalWeightKg: $body['goal_weight_kg'],
            activityLevel: ActivityLevel::from($body['activity_level']),
            goal: WeightGoal::from($body['goal']),
            approach: DietApproach::from($body['approach']),
        );
    }

    private function inputFromProfile(Profile $profile): MacroInput
    {
        return new MacroInput(
            sex: $profile->sex,
            ageYears: $profile->age(),
            heightCm: $profile->height_cm,
            weightKg: $profile->weight_kg,
            goalWeightKg: $profile->goal_weight_kg,
            activityLevel: $profile->activity_level,
            goal: $profile->goal,
            approach: $profile->approach,
        );
    }

    /** Same definition as Profile::age(): completed years as of now. */
    private function ageOn(Carbon $birthDate): int
    {
        return (int) $birthDate->diffInYears(now());
    }

    /** @return array<string, float> */
    private function targetColumns(MacroTargets $targets): array
    {
        return [
            'target_calories' => $targets->calories,
            'target_protein_g' => $targets->protein,
            'target_fat_g' => $targets->fat,
            'target_carbs_g' => $targets->carbs,
            'target_net_carbs_g' => $targets->netCarbs,
        ];
    }

    /** Rule::enum accepts either a backing value or an enum instance; normalise to the value. */
    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        return [
            'sex' => __('macros.fields.sex'),
            'birth_date' => __('macros.fields.birth_date'),
            'height_cm' => __('macros.fields.height_cm'),
            'weight_kg' => __('macros.fields.weight_kg'),
            'goal_weight_kg' => __('macros.fields.goal_weight_kg'),
            'activity_level' => __('macros.fields.activity_level'),
            'goal' => __('macros.fields.goal'),
            'approach' => __('macros.fields.approach'),
            'calories' => __('macros.fields.calories'),
            'protein_g' => __('macros.fields.protein_g'),
            'fat_g' => __('macros.fields.fat_g'),
            'net_carbs_g' => __('macros.fields.net_carbs_g'),
            'carbs_g' => __('macros.fields.carbs_g'),
        ];
    }
}
