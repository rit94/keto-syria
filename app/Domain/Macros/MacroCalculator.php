<?php

namespace App\Domain\Macros;

use App\Domain\Nutrition\MacroTargets;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;

/**
 * Personalised daily calorie and macro targets for a ketogenic / low-carb diet.
 *
 * Pure, deterministic arithmetic: the same MacroInput always yields the same MacroResult.
 * These are population-equation ESTIMATES, not a prescription – see lang/*\/macros.php
 * for the disclaimer the UI must show next to them.
 *
 * Pipeline (each step has its own method and docblock):
 *
 *  1. BMI                      weight / height²
 *  2. BMR                      Mifflin-St Jeor
 *  3. TDEE                     BMR × activity factor
 *  4. Goal adjustment          lose −20 % (capped) · maintain 0 · gain +10 %
 *                              – skipped entirely for underweight people and minors
 *  5. Safety floor             calories ≥ max(BMR, 1200 ♀ / 1500 ♂)
 *  6. Protein                  g/kg of a reference weight, capped at 35 % of calories
 *  7. Net carbs                fixed grams by approach
 *  8. Total carbs              net carbs + fibre allowance
 *  9. Fat                      whatever calories remain, ÷ 9
 * 10. Warnings / medical flag
 */
final class MacroCalculator
{
    // ── Step 2: Mifflin-St Jeor (1990) ─────────────────────────────────────────────
    // BMR = 10·kg + 6.25·cm − 5·age + s, with s = +5 (male) / −161 (female).
    // Chosen because it is the most accurate predictive equation for both normal-weight
    // and obese adults when body-fat percentage is unknown.
    public const float MIFFLIN_WEIGHT_COEFFICIENT = 10.0;

    public const float MIFFLIN_HEIGHT_COEFFICIENT = 6.25;

    public const float MIFFLIN_AGE_COEFFICIENT = 5.0;

    public const float MIFFLIN_MALE_CONSTANT = 5.0;

    public const float MIFFLIN_FEMALE_CONSTANT = -161.0;

    // ── Step 3: activity multipliers (standard Harris-Benedict/Katch activity scale) ──
    /** @var array<string, float> keyed by ActivityLevel value */
    public const array ACTIVITY_FACTORS = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'active' => 1.725,
        'very_active' => 1.9,
    ];

    // ── Step 4: goal adjustment ────────────────────────────────────────────────────
    /** Default deficit for weight loss: a moderate 20 % of TDEE. */
    public const float DEFAULT_DEFICIT_SHARE = 0.20;

    /** Hard ceiling on any deficit: never more than 25 % of TDEE. */
    public const float MAX_DEFICIT_SHARE = 0.25;

    /** Hard ceiling on the expected rate of loss: 1 % of body weight per week. */
    public const float MAX_WEEKLY_LOSS_SHARE = 0.01;

    /** Approximate energy content of 1 kg of body-fat tissue, used to convert kg/week ↔ kcal/day. */
    public const float KCAL_PER_KG_BODY_WEIGHT = 7700.0;

    public const float DAYS_PER_WEEK = 7.0;

    /** Surplus for weight gain: a lean +10 % of TDEE. */
    public const float SURPLUS_SHARE = 0.10;

    // ── Step 5: safety floors ──────────────────────────────────────────────────────
    /** Calories are never set below BMR × this factor (i.e. never below resting needs). */
    public const float BMR_FLOOR_FACTOR = 1.0;

    /** Absolute minimums below which nutrient adequacy cannot be assumed without supervision. */
    public const float MIN_CALORIES_FEMALE = 1200.0;

    public const float MIN_CALORIES_MALE = 1500.0;

    // ── BMI thresholds (WHO) ───────────────────────────────────────────────────────
    public const float BMI_UNDERWEIGHT = 18.5;

    public const float BMI_SEVERE_OBESITY = 40.0;

    // ── Step 6: protein ────────────────────────────────────────────────────────────
    /** Up to this BMI protein is based on actual weight; above it on an adjusted weight. */
    public const float PROTEIN_REFERENCE_BMI = 25.0;

    /** Adjusted weight = ideal + this share of the excess over ideal (classic dietetic 25 % rule). */
    public const float ADJUSTED_WEIGHT_EXCESS_SHARE = 0.25;

    /** @var array<string, float> base g/kg keyed by WeightGoal value */
    public const array PROTEIN_BASE_PER_KG = [
        'lose' => 1.6,      // higher protein protects lean mass in a deficit
        'maintain' => 1.4,
        'gain' => 1.6,      // supports muscle gain
    ];

    /** @var array<string, float> g/kg added for training load, keyed by ActivityLevel value */
    public const array PROTEIN_ACTIVITY_BONUS_PER_KG = [
        'sedentary' => 0.0,
        'light' => 0.1,
        'moderate' => 0.2,
        'active' => 0.3,
        'very_active' => 0.4,
    ];

    public const float PROTEIN_MIN_PER_KG = 1.4;

    public const float PROTEIN_MAX_PER_KG = 2.0;

    /** Protein never exceeds 35 % of calories (upper end of the accepted macronutrient range). */
    public const float PROTEIN_MAX_CALORIE_SHARE = 0.35;

    // ── Step 7: net carbs ──────────────────────────────────────────────────────────
    /** @var array<string, float> grams of NET carbs per day keyed by DietApproach value */
    public const array NET_CARBS_G = [
        'strict_keto' => 20.0,
        'standard_keto' => 25.0,
        'liberal_low_carb' => 50.0,
    ];

    /** Standard keto only: people who burn this much or more get the top of the 25–30 g range. */
    public const float STANDARD_KETO_HIGH_TDEE = 3000.0;

    public const float STANDARD_KETO_HIGH_TDEE_NET_CARBS_G = 30.0;

    // ── Step 8: fibre allowance ────────────────────────────────────────────────────
    /** Adequate-intake guideline: 14 g fibre per 1000 kcal. */
    public const float FIBER_G_PER_1000_KCAL = 14.0;

    /** Cap on the allowance so very high calorie targets do not inflate total carbs. */
    public const float FIBER_ALLOWANCE_CAP_G = 35.0;

    // ── Atwater factors ────────────────────────────────────────────────────────────
    public const float KCAL_PER_G_PROTEIN = 4.0;

    public const float KCAL_PER_G_CARBS = 4.0;

    public const float KCAL_PER_G_FAT = 9.0;

    // ── Step 9/10: plausibility and warning thresholds ─────────────────────────────
    /** On a keto/low-carb plan fat normally supplies well over half of the energy. */
    public const float MIN_FAT_CALORIE_SHARE = 0.50;

    /** Wanting to lose more than a quarter of current body weight is flagged as aggressive. */
    public const float AGGRESSIVE_LOSS_SHARE = 0.25;

    public const int ADULT_AGE = 18;

    public const int OLDER_ADULT_AGE = 65;

    /**
     * Conditions under which a ketogenic diet needs medical supervision.
     * Shown by the UI as a self-declaration checklist; the calculator takes no diagnosis input.
     *
     * @var list<string> translation keys
     */
    public const array MEDICAL_CONDITIONS = [
        'macros.conditions.diabetes_medication',       // insulin, SGLT2 inhibitors, sulfonylureas
        'macros.conditions.pregnancy_breastfeeding',
        'macros.conditions.kidney_disease',
        'macros.conditions.liver_disease',
        'macros.conditions.pancreatitis',
        'macros.conditions.gallbladder_disease',
        'macros.conditions.eating_disorder_history',
        'macros.conditions.blood_pressure_medication',
    ];

    /** @return list<string> translation keys of conditions requiring medical supervision */
    public static function medicalConditions(): array
    {
        return self::MEDICAL_CONDITIONS;
    }

    public function calculate(MacroInput $input): MacroResult
    {
        $warnings = [];
        $requiresMedicalAdvice = false;

        $bmi = $this->bmi($input->weightKg, $input->heightCm);
        $bmr = $this->bmr($input);
        $tdee = $this->tdee($input);

        $isMinor = $input->ageYears < self::ADULT_AGE;
        $isUnderweight = $bmi < self::BMI_UNDERWEIGHT;

        // Step 4 – goal adjustment. A deficit is never calculated for someone who is
        // already underweight or still growing: they get maintenance and a referral.
        $adjustment = match ($input->goal) {
            WeightGoal::Lose => ($isMinor || $isUnderweight) ? 0.0 : -$this->deficit($tdee, $input->weightKg),
            WeightGoal::Maintain => 0.0,
            WeightGoal::Gain => $tdee * self::SURPLUS_SHARE,
        };

        // Step 5 – safety floor.
        $floor = $this->calorieFloor($input);
        $calories = $tdee + $adjustment;
        if ($calories < $floor) {
            $calories = $floor;
            $warnings[] = MacroWarning::CALORIE_FLOOR_APPLIED;
        }

        // Step 6 – protein.
        $referenceWeight = $this->referenceWeight($input->weightKg, $input->heightCm);
        $proteinPerKg = $this->proteinPerKg($input);
        $protein = $proteinPerKg * $referenceWeight;
        $maxProtein = $calories * self::PROTEIN_MAX_CALORIE_SHARE / self::KCAL_PER_G_PROTEIN;
        if ($protein > $maxProtein) {
            $protein = $maxProtein;
            $proteinPerKg = $protein / $referenceWeight;
            $warnings[] = MacroWarning::PROTEIN_LIMITED;
        }

        // Steps 7 + 8 – carbs.
        $netCarbs = $this->netCarbs($input->approach, $tdee);
        $carbs = $netCarbs + $this->fiberAllowance($calories);

        // Step 9 – fat fills the remaining energy. Fibre is counted as energy-free here,
        // so calories = 4·protein + 4·net carbs + 9·fat exactly.
        $fatCalories = $calories
            - $protein * self::KCAL_PER_G_PROTEIN
            - $netCarbs * self::KCAL_PER_G_CARBS;
        $fat = $fatCalories / self::KCAL_PER_G_FAT;
        if ($fatCalories < $calories * self::MIN_FAT_CALORIE_SHARE) {
            // Do not quietly cut protein to make room – tell the user instead.
            $warnings[] = MacroWarning::LOW_FAT;
        }

        // Step 10 – person-level warnings.
        if ($isMinor) {
            $warnings[] = MacroWarning::AGE_UNDER_18;
            $requiresMedicalAdvice = true;
        }
        if ($input->ageYears >= self::OLDER_ADULT_AGE) {
            $warnings[] = MacroWarning::AGE_65_PLUS;
        }
        if ($isUnderweight) {
            $warnings[] = MacroWarning::BMI_UNDERWEIGHT;
            $requiresMedicalAdvice = true;
        }
        if ($bmi >= self::BMI_SEVERE_OBESITY) {
            $warnings[] = MacroWarning::BMI_40_PLUS;
            $requiresMedicalAdvice = true;
        }
        foreach ($this->goalWeightWarnings($input) as $warning) {
            $warnings[] = $warning;
        }

        return new MacroResult(
            targets: new MacroTargets(
                calories: $calories,
                protein: $protein,
                fat: $fat,
                carbs: $carbs,
                netCarbs: $netCarbs,
            ),
            bmr: $bmr,
            tdee: $tdee,
            calorieAdjustment: $calories - $tdee,
            proteinPerKg: $proteinPerKg,
            referenceWeightKg: $referenceWeight,
            warnings: $warnings,
            requiresMedicalAdvice: $requiresMedicalAdvice,
            bmi: $bmi,
            calorieFloor: $floor,
        );
    }

    /** Step 1 – body-mass index: kg / m². Used only for guards and reference weight, never shown as a verdict. */
    public function bmi(float $weightKg, float $heightCm): float
    {
        $heightM = $heightCm / 100.0;

        return $weightKg / ($heightM * $heightM);
    }

    /** Step 2 – basal metabolic rate (kcal/day) by Mifflin-St Jeor. */
    public function bmr(MacroInput $input): float
    {
        return self::MIFFLIN_WEIGHT_COEFFICIENT * $input->weightKg
            + self::MIFFLIN_HEIGHT_COEFFICIENT * $input->heightCm
            - self::MIFFLIN_AGE_COEFFICIENT * $input->ageYears
            + ($input->sex === Sex::Male ? self::MIFFLIN_MALE_CONSTANT : self::MIFFLIN_FEMALE_CONSTANT);
    }

    /** Step 3 – total daily energy expenditure (maintenance calories): BMR × activity factor. */
    public function tdee(MacroInput $input): float
    {
        return $this->bmr($input) * self::ACTIVITY_FACTORS[$input->activityLevel->value];
    }

    /**
     * Step 4 – daily deficit (kcal, positive number) for weight loss.
     *
     * The smallest of:
     *  - 20 % of TDEE (the default, moderate deficit),
     *  - 25 % of TDEE (hard ceiling, guards the default if it is ever tuned),
     *  - the deficit equivalent to losing 1 % of body weight per week
     *    (weight × 0.01 × 7700 kcal/kg ÷ 7 days = 11 kcal per kg per day).
     */
    public function deficit(float $tdee, float $weightKg): float
    {
        $weeklyLossCap = $weightKg * self::MAX_WEEKLY_LOSS_SHARE * self::KCAL_PER_KG_BODY_WEIGHT / self::DAYS_PER_WEEK;

        return min(
            $tdee * self::DEFAULT_DEFICIT_SHARE,
            $tdee * self::MAX_DEFICIT_SHARE,
            $weeklyLossCap,
        );
    }

    /**
     * Step 5 – the lowest calorie target this calculator will ever produce for the person,
     * and the lowest a custom target may be set to: the higher of their BMR and the
     * absolute minimum for their sex (1200 kcal female / 1500 kcal male).
     *
     * It applies to every goal. For a very small person whose TDEE is below the absolute
     * minimum this yields a target slightly above maintenance – intended: below those
     * levels a diet cannot be assumed nutritionally adequate without supervision.
     */
    public function calorieFloor(MacroInput $input): float
    {
        return max(
            $this->bmr($input) * self::BMR_FLOOR_FACTOR,
            $input->sex === Sex::Male ? self::MIN_CALORIES_MALE : self::MIN_CALORIES_FEMALE,
        );
    }

    /**
     * Step 6a – weight the protein target is based on.
     *
     * BMI ≤ 25: actual weight.
     * BMI > 25: ideal weight (the weight at BMI 25 for this height) plus 25 % of the excess,
     * because the extra mass is mostly fat tissue that needs little protein; using actual
     * weight would over-prescribe protein for people with obesity. Continuous at BMI 25.
     */
    public function referenceWeight(float $weightKg, float $heightCm): float
    {
        if ($this->bmi($weightKg, $heightCm) <= self::PROTEIN_REFERENCE_BMI) {
            return $weightKg;
        }

        $heightM = $heightCm / 100.0;
        $idealWeight = self::PROTEIN_REFERENCE_BMI * $heightM * $heightM;

        return $idealWeight + self::ADJUSTED_WEIGHT_EXCESS_SHARE * ($weightKg - $idealWeight);
    }

    /**
     * Step 6b – protein density: base for the goal + bonus for activity, kept within 1.4–2.0 g/kg.
     * (calculate() may lower the effective figure further via the 35 %-of-calories cap.)
     */
    public function proteinPerKg(MacroInput $input): float
    {
        $perKg = self::PROTEIN_BASE_PER_KG[$input->goal->value]
            + self::PROTEIN_ACTIVITY_BONUS_PER_KG[$input->activityLevel->value];

        return max(self::PROTEIN_MIN_PER_KG, min(self::PROTEIN_MAX_PER_KG, $perKg));
    }

    /**
     * Step 7 – daily NET carbs (total carbs − fibre) in grams.
     *
     * Fixed grams, not a percentage of calories: ketosis depends on absolute carbohydrate
     * intake, so a bigger eater does not get proportionally more carbs.
     *   strict keto 20 g · standard keto 25 g · liberal low-carb 50 g.
     * Only standard keto is scaled, and only slightly: at a TDEE of 3000 kcal or more
     * (large and/or very active people, who oxidise more glucose) it becomes 30 g – the
     * top of the usual 25–30 g range. Strict stays strict; liberal is already generous.
     */
    public function netCarbs(DietApproach $approach, float $tdee): float
    {
        if ($approach === DietApproach::StandardKeto && $tdee >= self::STANDARD_KETO_HIGH_TDEE) {
            return self::STANDARD_KETO_HIGH_TDEE_NET_CARBS_G;
        }

        return self::NET_CARBS_G[$approach->value];
    }

    /**
     * Step 8 – fibre allowance in grams: 14 g per 1000 kcal of the calorie target, capped at 35 g.
     *
     * TOTAL carbs target = net carbs + this allowance. It exists so that eating vegetables,
     * nuts and seeds is never discouraged by the total-carb number; the net-carb target is
     * the one that actually constrains the diet.
     */
    public function fiberAllowance(float $calories): float
    {
        return min(self::FIBER_ALLOWANCE_CAP_G, self::FIBER_G_PER_1000_KCAL * $calories / 1000.0);
    }

    /**
     * Step 10 – checks on the chosen goal weight.
     *
     * @return list<string>
     */
    private function goalWeightWarnings(MacroInput $input): array
    {
        $goalWeight = $input->goalWeightKg;
        if ($goalWeight === null) {
            return [];
        }

        $warnings = [];

        if ($this->bmi($goalWeight, $input->heightCm) < self::BMI_UNDERWEIGHT) {
            $warnings[] = MacroWarning::GOAL_WEIGHT_UNDERWEIGHT;
        }

        if ($input->goal === WeightGoal::Lose) {
            if ($goalWeight >= $input->weightKg) {
                $warnings[] = MacroWarning::GOAL_WEIGHT_MISMATCH;
            } elseif (($input->weightKg - $goalWeight) / $input->weightKg > self::AGGRESSIVE_LOSS_SHARE) {
                $warnings[] = MacroWarning::AGGRESSIVE_GOAL;
            }
        }

        if ($input->goal === WeightGoal::Gain && $goalWeight <= $input->weightKg) {
            $warnings[] = MacroWarning::GOAL_WEIGHT_MISMATCH;
        }

        return $warnings;
    }
}
