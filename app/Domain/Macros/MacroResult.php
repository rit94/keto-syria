<?php

namespace App\Domain\Macros;

use App\Domain\Nutrition\MacroTargets;

/**
 * Outcome of one calculation, at full float precision, together with the
 * intermediate figures needed to explain it to the user.
 */
final readonly class MacroResult
{
    /** Calories are saved/displayed to the nearest 10 kcal – finer precision would be false accuracy. */
    public const float CALORIE_ROUNDING_STEP = 10.0;

    /**
     * @param  MacroTargets  $targets  daily targets, unrounded
     * @param  float  $bmr  basal metabolic rate, kcal/day
     * @param  float  $tdee  maintenance energy, kcal/day
     * @param  float  $calorieAdjustment  target calories − TDEE (negative = deficit), after safety floors
     * @param  float  $proteinPerKg  effective g protein per kg of reference weight (after any clamp)
     * @param  float  $referenceWeightKg  weight the protein target is based on
     * @param  list<string>  $warnings  translation keys (see MacroWarning)
     * @param  bool  $requiresMedicalAdvice  true when the person should not follow these numbers without a clinician
     * @param  float  $bmi  current body-mass index
     * @param  float  $calorieFloor  lowest calorie target considered safe for this person
     */
    public function __construct(
        public MacroTargets $targets,
        public float $bmr,
        public float $tdee,
        public float $calorieAdjustment,
        public float $proteinPerKg,
        public float $referenceWeightKg,
        public array $warnings,
        public bool $requiresMedicalAdvice,
        public float $bmi,
        public float $calorieFloor,
    ) {}

    /**
     * Targets rounded for saving/display: calories to the nearest 10 kcal, grams to the nearest 1 g.
     *
     * The one exception: if rounding to the nearest 10 would land below the safety floor
     * (e.g. floor 1564 → 1560) calories are rounded UP instead, so a saved target is never
     * below the floor.
     */
    public function roundedTargets(): MacroTargets
    {
        $step = self::CALORIE_ROUNDING_STEP;
        $calories = round($this->targets->calories / $step) * $step;

        if ($calories < $this->calorieFloor) {
            $calories = ceil($this->targets->calories / $step) * $step;
        }

        return new MacroTargets(
            calories: $calories,
            protein: round($this->targets->protein),
            fat: round($this->targets->fat),
            carbs: round($this->targets->carbs),
            netCarbs: round($this->targets->netCarbs),
        );
    }

    public function hasWarning(string $key): bool
    {
        return in_array($key, $this->warnings, true);
    }
}
