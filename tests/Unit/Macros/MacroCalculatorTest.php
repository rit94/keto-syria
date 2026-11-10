<?php

use App\Domain\Macros\MacroCalculator;
use App\Domain\Macros\MacroInput;
use App\Domain\Macros\MacroResult;
use App\Domain\Macros\MacroWarning;
use App\Domain\Nutrition\MacroTargets;
use App\Enums\ActivityLevel;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;

/**
 * All expected values below are computed by hand from the documented method:
 *   BMR  = 10·kg + 6.25·cm − 5·age + (5 | −161)
 *   TDEE = BMR × (1.2 | 1.375 | 1.55 | 1.725 | 1.9)
 *   lose = −20 % (≤ 25 %, ≤ 11 kcal/kg/day) · gain = +10 %
 *   floor = max(BMR, 1200 ♀ | 1500 ♂)
 *   protein = g/kg × reference weight, ≤ 35 % of kcal
 *   fat = (kcal − 4·protein − 4·net carbs) / 9
 *   carbs = net + min(35, 14 g per 1000 kcal)
 */
function macroInput(
    Sex $sex,
    int $age,
    float $height,
    float $weight,
    ActivityLevel $activity,
    WeightGoal $goal,
    DietApproach $approach = DietApproach::StandardKeto,
    ?float $goalWeight = null,
): MacroInput {
    return new MacroInput($sex, $age, $height, $weight, $goalWeight, $activity, $goal, $approach);
}

function macroCalc(MacroInput $input): MacroResult
{
    return (new MacroCalculator)->calculate($input);
}

const MACRO_DELTA = 1e-6;

describe('personas', function () {
    it('30y male, 175 cm, 85 kg, light, lose, standard keto', function () {
        $r = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose, DietApproach::StandardKeto, 75));

        // BMI 85 / 1.75² = 27.755
        // BMR 850 + 1093.75 − 150 + 5 = 1798.75 ; TDEE × 1.375 = 2473.28125
        // deficit 20 % = 494.65625 → 1978.625 (floor 1798.75 not reached)
        // ideal 25 × 3.0625 = 76.5625 ; reference 76.5625 + 0.25 × 8.4375 = 78.671875
        // protein 1.7 × 78.671875 = 133.7421875 g (534.96875 kcal)
        // fat (1978.625 − 534.96875 − 100) / 9 = 149.2951389
        // fibre 14 × 1.978625 = 27.70075 → carbs 52.70075
        expect($r->bmi)->toEqualWithDelta(27.755102, MACRO_DELTA)
            ->and($r->bmr)->toEqualWithDelta(1798.75, MACRO_DELTA)
            ->and($r->tdee)->toEqualWithDelta(2473.28125, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(-494.65625, MACRO_DELTA)
            ->and($r->calorieFloor)->toEqualWithDelta(1798.75, MACRO_DELTA)
            ->and($r->referenceWeightKg)->toEqualWithDelta(78.671875, MACRO_DELTA)
            ->and($r->proteinPerKg)->toEqualWithDelta(1.7, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(1978.625, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(133.7421875, MACRO_DELTA)
            ->and($r->targets->fat)->toEqualWithDelta(149.2951389, MACRO_DELTA)
            ->and($r->targets->netCarbs)->toEqualWithDelta(25.0, MACRO_DELTA)
            ->and($r->targets->carbs)->toEqualWithDelta(52.70075, MACRO_DELTA)
            ->and($r->warnings)->toBe([])
            ->and($r->requiresMedicalAdvice)->toBeFalse();

        expect($r->roundedTargets()->toArray())->toBe([
            'calories' => 1980.0, 'protein_g' => 134.0, 'fat_g' => 149.0, 'carbs_g' => 53.0, 'net_carbs_g' => 25.0,
        ]);
    });

    it('45y female, 160 cm, 95 kg, sedentary, lose, strict keto → BMR floor and adjusted protein weight', function () {
        $r = macroCalc(macroInput(Sex::Female, 45, 160, 95, ActivityLevel::Sedentary, WeightGoal::Lose, DietApproach::StrictKeto));

        // BMI 95 / 2.56 = 37.109
        // BMR 950 + 1000 − 225 − 161 = 1564 ; TDEE × 1.2 = 1876.8
        // 20 % deficit → 1501.44 < floor 1564 → 1564 (effective deficit 312.8)
        // ideal 25 × 2.56 = 64 ; reference 64 + 0.25 × 31 = 71.75 (not 95!)
        // protein 1.6 × 71.75 = 114.8 g (459.2 kcal)
        // fat (1564 − 459.2 − 80) / 9 = 113.8666667
        // fibre 14 × 1.564 = 21.896 → carbs 41.896
        expect($r->bmi)->toEqualWithDelta(37.109375, MACRO_DELTA)
            ->and($r->bmr)->toEqualWithDelta(1564.0, MACRO_DELTA)
            ->and($r->tdee)->toEqualWithDelta(1876.8, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(1564.0, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(-312.8, MACRO_DELTA)
            ->and($r->referenceWeightKg)->toEqualWithDelta(71.75, MACRO_DELTA)
            ->and($r->proteinPerKg)->toEqualWithDelta(1.6, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(114.8, MACRO_DELTA)
            ->and($r->targets->fat)->toEqualWithDelta(113.8666667, MACRO_DELTA)
            ->and($r->targets->netCarbs)->toEqualWithDelta(20.0, MACRO_DELTA)
            ->and($r->targets->carbs)->toEqualWithDelta(41.896, MACRO_DELTA)
            ->and($r->warnings)->toBe([MacroWarning::CALORIE_FLOOR_APPLIED])
            ->and($r->requiresMedicalAdvice)->toBeFalse();

        // Nearest 10 would be 1560, which is below the 1564 floor → rounded up instead.
        expect($r->roundedTargets()->toArray())->toBe([
            'calories' => 1570.0, 'protein_g' => 115.0, 'fat_g' => 114.0, 'carbs_g' => 42.0, 'net_carbs_g' => 20.0,
        ]);
    });

    it('22y female, 165 cm, 52 kg, active, wants 47 kg → deficit allowed but goal weight flagged', function () {
        $r = macroCalc(macroInput(Sex::Female, 22, 165, 52, ActivityLevel::Active, WeightGoal::Lose, DietApproach::StandardKeto, 47));

        // BMI 52 / 2.7225 = 19.10 (healthy) ; goal BMI 47 / 2.7225 = 17.26 (underweight)
        // BMR 520 + 1031.25 − 110 − 161 = 1280.25 ; TDEE × 1.725 = 2208.43125
        // deficit 441.68625 → 1766.745 (floor 1280.25 not reached)
        // protein 1.9 × 52 = 98.8 g ; fat (1766.745 − 395.2 − 100) / 9 = 141.2827778
        // fibre 14 × 1.766745 = 24.73443 → carbs 49.73443
        expect($r->bmi)->toEqualWithDelta(19.100092, MACRO_DELTA)
            ->and($r->bmr)->toEqualWithDelta(1280.25, MACRO_DELTA)
            ->and($r->tdee)->toEqualWithDelta(2208.43125, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(1766.745, MACRO_DELTA)
            ->and($r->referenceWeightKg)->toEqualWithDelta(52.0, MACRO_DELTA)
            ->and($r->proteinPerKg)->toEqualWithDelta(1.9, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(98.8, MACRO_DELTA)
            ->and($r->targets->fat)->toEqualWithDelta(141.2827778, MACRO_DELTA)
            ->and($r->targets->carbs)->toEqualWithDelta(49.73443, MACRO_DELTA)
            ->and($r->warnings)->toBe([MacroWarning::GOAL_WEIGHT_UNDERWEIGHT])
            ->and($r->requiresMedicalAdvice)->toBeFalse();
    });

    it('22y female, 165 cm, 49 kg, active, wants to lose → underweight guard: no deficit, medical advice', function () {
        $r = macroCalc(macroInput(Sex::Female, 22, 165, 49, ActivityLevel::Active, WeightGoal::Lose));

        // BMI 49 / 2.7225 = 17.998 < 18.5
        // BMR 490 + 1031.25 − 110 − 161 = 1250.25 ; TDEE × 1.725 = 2156.68125 → maintenance
        expect($r->bmi)->toBeLessThan(18.5)
            ->and($r->tdee)->toEqualWithDelta(2156.68125, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(2156.68125, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(0.0, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(93.1, MACRO_DELTA)
            ->and($r->warnings)->toBe([MacroWarning::BMI_UNDERWEIGHT])
            ->and($r->requiresMedicalAdvice)->toBeTrue();
    });

    it('70y male, 170 cm, 80 kg, moderate, maintain, liberal low-carb', function () {
        $r = macroCalc(macroInput(Sex::Male, 70, 170, 80, ActivityLevel::Moderate, WeightGoal::Maintain, DietApproach::LiberalLowCarb));

        // BMR 800 + 1062.5 − 350 + 5 = 1517.5 ; TDEE × 1.55 = 2352.125
        // ideal 25 × 2.89 = 72.25 ; reference 72.25 + 0.25 × 7.75 = 74.1875
        // protein 1.6 × 74.1875 = 118.7 g (474.8 kcal)
        // fat (2352.125 − 474.8 − 200) / 9 = 186.3694444
        // fibre 14 × 2.352125 = 32.92975 → carbs 82.92975
        expect($r->bmr)->toEqualWithDelta(1517.5, MACRO_DELTA)
            ->and($r->tdee)->toEqualWithDelta(2352.125, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(2352.125, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(0.0, MACRO_DELTA)
            ->and($r->referenceWeightKg)->toEqualWithDelta(74.1875, MACRO_DELTA)
            ->and($r->proteinPerKg)->toEqualWithDelta(1.6, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(118.7, MACRO_DELTA)
            ->and($r->targets->fat)->toEqualWithDelta(186.3694444, MACRO_DELTA)
            ->and($r->targets->netCarbs)->toEqualWithDelta(50.0, MACRO_DELTA)
            ->and($r->targets->carbs)->toEqualWithDelta(82.92975, MACRO_DELTA)
            ->and($r->warnings)->toBe([MacroWarning::AGE_65_PLUS])
            ->and($r->requiresMedicalAdvice)->toBeFalse();
    });

    it('25y male, 200 cm, 100 kg, very active, gain, standard keto → 30 g net carbs, fibre cap', function () {
        $r = macroCalc(macroInput(Sex::Male, 25, 200, 100, ActivityLevel::VeryActive, WeightGoal::Gain));

        // BMI exactly 25 → actual weight is the reference
        // BMR 1000 + 1250 − 125 + 5 = 2130 ; TDEE × 1.9 = 4047 ; +10 % = 4451.7
        // protein 2.0 × 100 = 200 g (800 kcal, 18 %)
        // TDEE ≥ 3000 on standard keto → 30 g net (120 kcal)
        // fat (4451.7 − 800 − 120) / 9 = 392.4111111
        // fibre 14 × 4.4517 = 62.3 → capped at 35 → carbs 65
        expect($r->bmi)->toEqualWithDelta(25.0, MACRO_DELTA)
            ->and($r->bmr)->toEqualWithDelta(2130.0, MACRO_DELTA)
            ->and($r->tdee)->toEqualWithDelta(4047.0, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(404.7, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(4451.7, MACRO_DELTA)
            ->and($r->referenceWeightKg)->toEqualWithDelta(100.0, MACRO_DELTA)
            ->and($r->proteinPerKg)->toEqualWithDelta(2.0, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(200.0, MACRO_DELTA)
            ->and($r->targets->netCarbs)->toEqualWithDelta(30.0, MACRO_DELTA)
            ->and($r->targets->fat)->toEqualWithDelta(392.4111111, MACRO_DELTA)
            ->and($r->targets->carbs)->toEqualWithDelta(65.0, MACRO_DELTA)
            ->and($r->warnings)->toBe([])
            ->and($r->requiresMedicalAdvice)->toBeFalse();

        expect($r->roundedTargets()->toArray())->toBe([
            'calories' => 4450.0, 'protein_g' => 200.0, 'fat_g' => 392.0, 'carbs_g' => 65.0, 'net_carbs_g' => 30.0,
        ]);
    });
});

describe('energy', function () {
    it('uses the documented activity factors', function (ActivityLevel $level, float $factor) {
        // Male 30y 175 cm 85 kg → BMR 1798.75
        $r = macroCalc(macroInput(Sex::Male, 30, 175, 85, $level, WeightGoal::Maintain));

        expect($r->tdee)->toEqualWithDelta(1798.75 * $factor, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(1798.75 * $factor, MACRO_DELTA);
    })->with([
        [ActivityLevel::Sedentary, 1.2],
        [ActivityLevel::Light, 1.375],
        [ActivityLevel::Moderate, 1.55],
        [ActivityLevel::Active, 1.725],
        [ActivityLevel::VeryActive, 1.9],
    ]);

    it('differs between sexes by exactly 166 kcal of BMR', function () {
        $male = macroCalc(macroInput(Sex::Male, 40, 170, 70, ActivityLevel::Light, WeightGoal::Maintain));
        $female = macroCalc(macroInput(Sex::Female, 40, 170, 70, ActivityLevel::Light, WeightGoal::Maintain));

        expect($male->bmr - $female->bmr)->toEqualWithDelta(166.0, MACRO_DELTA);
    });

    it('caps the deficit at 1% of body weight per week and at 25% of TDEE', function () {
        $calculator = new MacroCalculator;

        // 20 % of 2000 = 400 ; 1 %/week of 80 kg = 80 × 0.01 × 7700 / 7 = 880 → 400
        expect($calculator->deficit(2000, 80))->toEqualWithDelta(400.0, MACRO_DELTA);
        // 20 % of 4000 = 800 ; 1 %/week of 50 kg = 550 → 550
        expect($calculator->deficit(4000, 50))->toEqualWithDelta(550.0, MACRO_DELTA);
        // The default must itself respect the hard ceiling.
        expect(MacroCalculator::DEFAULT_DEFICIT_SHARE)->toBeLessThanOrEqual(MacroCalculator::MAX_DEFICIT_SHARE);
    });

    it('never produces a deficit larger than 25% of TDEE for any activity level', function (ActivityLevel $level) {
        $r = macroCalc(macroInput(Sex::Male, 35, 180, 110, $level, WeightGoal::Lose));

        expect(-$r->calorieAdjustment)->toBeLessThanOrEqual($r->tdee * 0.25)
            ->and($r->targets->calories)->toBeGreaterThanOrEqual($r->bmr);
    })->with(ActivityLevel::cases());

    it('adds a 10% surplus for gain and nothing for maintain', function () {
        $gain = macroCalc(macroInput(Sex::Female, 28, 170, 58, ActivityLevel::Moderate, WeightGoal::Gain));
        $maintain = macroCalc(macroInput(Sex::Female, 28, 170, 58, ActivityLevel::Moderate, WeightGoal::Maintain));

        // BMR 580 + 1062.5 − 140 − 161 = 1341.5 ; TDEE × 1.55 = 2079.325
        expect($maintain->targets->calories)->toEqualWithDelta(2079.325, MACRO_DELTA)
            ->and($maintain->calorieAdjustment)->toEqualWithDelta(0.0, MACRO_DELTA)
            ->and($gain->calorieAdjustment)->toEqualWithDelta(207.9325, MACRO_DELTA)
            ->and($gain->targets->calories)->toEqualWithDelta(2287.2575, MACRO_DELTA);
    });
});

describe('safety floors', function () {
    it('never goes below 1200 kcal for a woman', function () {
        // 75y, 150 cm, 45 kg (BMI 20), sedentary, lose
        // BMR 450 + 937.5 − 375 − 161 = 851.5 ; TDEE 1021.8 ; 20 % deficit → 817.44 → floor 1200
        $r = macroCalc(macroInput(Sex::Female, 75, 150, 45, ActivityLevel::Sedentary, WeightGoal::Lose));

        expect($r->calorieFloor)->toEqualWithDelta(1200.0, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(1200.0, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(178.2, MACRO_DELTA)
            ->and($r->hasWarning(MacroWarning::CALORIE_FLOOR_APPLIED))->toBeTrue();
    });

    it('never goes below 1500 kcal for a man, even on maintenance', function () {
        // 80y, 155 cm, 45 kg (BMI 18.73), sedentary, maintain
        // BMR 450 + 968.75 − 400 + 5 = 1023.75 ; TDEE 1228.5 → floor 1500
        $r = macroCalc(macroInput(Sex::Male, 80, 155, 45, ActivityLevel::Sedentary, WeightGoal::Maintain));

        expect($r->tdee)->toEqualWithDelta(1228.5, MACRO_DELTA)
            ->and($r->calorieFloor)->toEqualWithDelta(1500.0, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(1500.0, MACRO_DELTA)
            ->and($r->warnings)->toEqualCanonicalizing([MacroWarning::CALORIE_FLOOR_APPLIED, MacroWarning::AGE_65_PLUS]);
    });

    it('never goes below BMR when BMR is above the absolute minimum', function () {
        // Male 40y 180 cm 135 kg sedentary: BMR 1350 + 1125 − 200 + 5 = 2280 ; TDEE 2736 ; −20 % = 2188.8 → 2280
        $r = macroCalc(macroInput(Sex::Male, 40, 180, 135, ActivityLevel::Sedentary, WeightGoal::Lose));

        expect($r->calorieFloor)->toEqualWithDelta(2280.0, MACRO_DELTA)
            ->and($r->targets->calories)->toEqualWithDelta(2280.0, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(-456.0, MACRO_DELTA)
            ->and($r->hasWarning(MacroWarning::CALORIE_FLOOR_APPLIED))->toBeTrue();
    });

    it('does not warn about the floor when it is not reached', function () {
        $r = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose));

        expect($r->hasWarning(MacroWarning::CALORIE_FLOOR_APPLIED))->toBeFalse()
            ->and($r->targets->calories)->toBeGreaterThan($r->calorieFloor);
    });

    it('keeps every result at or above the floor across a grid of people', function () {
        $calculator = new MacroCalculator;

        foreach (Sex::cases() as $sex) {
            foreach ([14, 25, 50, 90] as $age) {
                foreach ([[125, 30], [150, 45], [165, 60], [180, 120], [200, 250]] as [$height, $weight]) {
                    foreach (ActivityLevel::cases() as $activity) {
                        foreach (WeightGoal::cases() as $goal) {
                            $r = $calculator->calculate(macroInput($sex, $age, $height, $weight, $activity, $goal));
                            $minimum = $sex === Sex::Male ? 1500.0 : 1200.0;

                            expect($r->targets->calories)->toBeGreaterThanOrEqual($minimum)
                                ->and($r->targets->calories)->toBeGreaterThanOrEqual($r->bmr)
                                ->and($r->roundedTargets()->calories)->toBeGreaterThanOrEqual($r->calorieFloor)
                                ->and($r->targets->protein)->toBeGreaterThan(0.0)
                                ->and($r->targets->fat)->toBeGreaterThan(0.0);
                        }
                    }
                }
            }
        }
    });
});

describe('protein', function () {
    it('uses actual weight up to BMI 25 and an adjusted weight above it', function () {
        $calculator = new MacroCalculator;

        // 170 cm: ideal = 25 × 2.89 = 72.25 kg
        expect($calculator->referenceWeight(65, 170))->toEqualWithDelta(65.0, MACRO_DELTA)
            ->and($calculator->referenceWeight(72.25, 170))->toEqualWithDelta(72.25, MACRO_DELTA)
            // 72.25 + 0.25 × (120 − 72.25) = 84.1875 – far below the actual 120 kg
            ->and($calculator->referenceWeight(120, 170))->toEqualWithDelta(84.1875, MACRO_DELTA);
    });

    it('scales grams per kg with goal and activity inside 1.4–2.0', function (WeightGoal $goal, ActivityLevel $activity, float $expected) {
        // 175 cm, 70 kg → BMI 22.9, reference = actual weight, protein share well under 35 %
        $r = macroCalc(macroInput(Sex::Male, 30, 175, 70, $activity, $goal));

        expect($r->proteinPerKg)->toEqualWithDelta($expected, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta($expected * 70, MACRO_DELTA);
    })->with([
        [WeightGoal::Maintain, ActivityLevel::Sedentary, 1.4],
        [WeightGoal::Maintain, ActivityLevel::Moderate, 1.6],
        [WeightGoal::Maintain, ActivityLevel::VeryActive, 1.8],
        [WeightGoal::Lose, ActivityLevel::Sedentary, 1.6],
        [WeightGoal::Lose, ActivityLevel::Light, 1.7],
        [WeightGoal::Lose, ActivityLevel::Active, 1.9],
        [WeightGoal::Lose, ActivityLevel::VeryActive, 2.0],
        [WeightGoal::Gain, ActivityLevel::Light, 1.7],
        [WeightGoal::Gain, ActivityLevel::VeryActive, 2.0],
    ]);

    it('caps protein at 35% of calories and warns when fat ends up below half of calories', function () {
        // Female 85y, 157 cm, 62 kg, moderate, lose, liberal low-carb
        // BMI 62 / 2.4649 = 25.153 → ideal 61.6225 ; reference 61.6225 + 0.25 × 0.3775 = 61.716875
        // BMR 620 + 981.25 − 425 − 161 = 1015.25 ; TDEE × 1.55 = 1573.6375 ; −20 % = 1258.91 (≥ floor 1200)
        // wanted protein 1.8 × 61.716875 = 111.090375 g = 444.36 kcal = 35.3 % → capped at 440.6185 kcal = 110.154625 g
        // fat kcal 1258.91 − 440.6185 − 200 = 618.2915 (49.1 % < 50 %) → 68.6990556 g
        $r = macroCalc(macroInput(Sex::Female, 85, 157, 62, ActivityLevel::Moderate, WeightGoal::Lose, DietApproach::LiberalLowCarb));

        expect($r->targets->calories)->toEqualWithDelta(1258.91, MACRO_DELTA)
            ->and($r->referenceWeightKg)->toEqualWithDelta(61.716875, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(110.154625, MACRO_DELTA)
            ->and($r->proteinPerKg)->toEqualWithDelta(110.154625 / 61.716875, MACRO_DELTA)
            ->and($r->proteinPerKg)->toBeLessThan(1.8)
            ->and($r->targets->fat)->toEqualWithDelta(68.6990556, MACRO_DELTA)
            ->and($r->warnings)->toEqualCanonicalizing([
                MacroWarning::PROTEIN_LIMITED,
                MacroWarning::LOW_FAT,
                MacroWarning::AGE_65_PLUS,
            ]);
    });
});

describe('carbs', function () {
    it('sets fixed net carbs per approach', function (DietApproach $approach, float $net) {
        // Male 30y 175 cm 85 kg light lose → 1978.625 kcal, fibre allowance 27.70075 g
        $r = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose, $approach));

        expect($r->targets->netCarbs)->toEqualWithDelta($net, MACRO_DELTA)
            ->and($r->targets->carbs)->toEqualWithDelta($net + 27.70075, MACRO_DELTA)
            // protein is unaffected by the approach; fat absorbs the difference
            ->and($r->targets->protein)->toEqualWithDelta(133.7421875, MACRO_DELTA)
            ->and($r->targets->fat)->toEqualWithDelta((1978.625 - 534.96875 - $net * 4) / 9, MACRO_DELTA);
    })->with([
        [DietApproach::StrictKeto, 20.0],
        [DietApproach::StandardKeto, 25.0],
        [DietApproach::LiberalLowCarb, 50.0],
    ]);

    it('raises only standard keto to 30 g at a TDEE of 3000 kcal or more', function () {
        $calculator = new MacroCalculator;

        expect($calculator->netCarbs(DietApproach::StandardKeto, 2999.9))->toBe(25.0)
            ->and($calculator->netCarbs(DietApproach::StandardKeto, 3000.0))->toBe(30.0)
            ->and($calculator->netCarbs(DietApproach::StrictKeto, 4500.0))->toBe(20.0)
            ->and($calculator->netCarbs(DietApproach::LiberalLowCarb, 4500.0))->toBe(50.0);
    });

    it('allows 14 g of fibre per 1000 kcal, capped at 35 g', function () {
        $calculator = new MacroCalculator;

        expect($calculator->fiberAllowance(1500))->toEqualWithDelta(21.0, MACRO_DELTA)
            ->and($calculator->fiberAllowance(2500))->toEqualWithDelta(35.0, MACRO_DELTA)
            ->and($calculator->fiberAllowance(4000))->toEqualWithDelta(35.0, MACRO_DELTA);
    });
});

describe('warnings and medical flags', function () {
    it('gives minors maintenance calories and requires medical advice', function () {
        // Male 16y 170 cm 60 kg light: BMR 600 + 1062.5 − 80 + 5 = 1587.5 ; TDEE 2182.8125
        $r = macroCalc(macroInput(Sex::Male, 16, 170, 60, ActivityLevel::Light, WeightGoal::Lose));

        expect($r->targets->calories)->toEqualWithDelta(2182.8125, MACRO_DELTA)
            ->and($r->calorieAdjustment)->toEqualWithDelta(0.0, MACRO_DELTA)
            ->and($r->warnings)->toBe([MacroWarning::AGE_UNDER_18])
            ->and($r->requiresMedicalAdvice)->toBeTrue();
    });

    it('treats 18 as adult and 65 as older adult (boundaries)', function () {
        $at17 = macroCalc(macroInput(Sex::Male, 17, 175, 70, ActivityLevel::Light, WeightGoal::Maintain));
        $at18 = macroCalc(macroInput(Sex::Male, 18, 175, 70, ActivityLevel::Light, WeightGoal::Maintain));
        $at64 = macroCalc(macroInput(Sex::Male, 64, 175, 70, ActivityLevel::Light, WeightGoal::Maintain));
        $at65 = macroCalc(macroInput(Sex::Male, 65, 175, 70, ActivityLevel::Light, WeightGoal::Maintain));

        expect($at17->hasWarning(MacroWarning::AGE_UNDER_18))->toBeTrue()
            ->and($at18->warnings)->toBe([])
            ->and($at64->warnings)->toBe([])
            ->and($at65->warnings)->toBe([MacroWarning::AGE_65_PLUS])
            ->and($at65->requiresMedicalAdvice)->toBeFalse();
    });

    it('flags underweight people whatever their goal', function (WeightGoal $goal) {
        // 180 cm, 55 kg → BMI 16.98
        $r = macroCalc(macroInput(Sex::Male, 30, 180, 55, ActivityLevel::Light, $goal));

        expect($r->hasWarning(MacroWarning::BMI_UNDERWEIGHT))->toBeTrue()
            ->and($r->requiresMedicalAdvice)->toBeTrue()
            ->and($r->calorieAdjustment)->toBeGreaterThanOrEqual(0.0);
    })->with(WeightGoal::cases());

    it('flags BMI 40+ and requires medical advice, but still calculates', function () {
        // Male 40y 180 cm 135 kg → BMI 41.67 ; reference 81 + 0.25 × 54 = 94.5 ; protein 1.6 × 94.5 = 151.2
        $r = macroCalc(macroInput(Sex::Male, 40, 180, 135, ActivityLevel::Sedentary, WeightGoal::Lose));

        expect($r->bmi)->toEqualWithDelta(41.6666667, MACRO_DELTA)
            ->and($r->referenceWeightKg)->toEqualWithDelta(94.5, MACRO_DELTA)
            ->and($r->targets->protein)->toEqualWithDelta(151.2, MACRO_DELTA)
            ->and($r->warnings)->toEqualCanonicalizing([MacroWarning::CALORIE_FLOOR_APPLIED, MacroWarning::BMI_40_PLUS])
            ->and($r->requiresMedicalAdvice)->toBeTrue();
    });

    it('flags an aggressive goal when more than a quarter of body weight is to be lost', function () {
        // 85 → 60 kg = 29.4 % ; goal BMI 19.6 is still healthy
        $aggressive = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose, goalWeight: 60));
        // 85 → 70 kg = 17.6 %
        $moderate = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose, goalWeight: 70));

        expect($aggressive->warnings)->toBe([MacroWarning::AGGRESSIVE_GOAL])
            ->and($moderate->warnings)->toBe([])
            // the goal weight never changes the numbers, only the warnings
            ->and($aggressive->targets)->toEqual($moderate->targets);
    });

    it('flags a goal weight that contradicts the goal', function () {
        $loseUp = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose, goalWeight: 90));
        $gainDown = macroCalc(macroInput(Sex::Male, 30, 175, 70, ActivityLevel::Light, WeightGoal::Gain, goalWeight: 65));
        $gainUp = macroCalc(macroInput(Sex::Male, 30, 175, 70, ActivityLevel::Light, WeightGoal::Gain, goalWeight: 75));

        expect($loseUp->warnings)->toBe([MacroWarning::GOAL_WEIGHT_MISMATCH])
            ->and($gainDown->warnings)->toBe([MacroWarning::GOAL_WEIGHT_MISMATCH])
            ->and($gainUp->warnings)->toBe([]);
    });

    it('exposes the conditions that need medical supervision as translation keys', function () {
        expect(MacroCalculator::medicalConditions())->toBe([
            'macros.conditions.diabetes_medication',
            'macros.conditions.pregnancy_breastfeeding',
            'macros.conditions.kidney_disease',
            'macros.conditions.liver_disease',
            'macros.conditions.pancreatitis',
            'macros.conditions.gallbladder_disease',
            'macros.conditions.eating_disorder_history',
            'macros.conditions.blood_pressure_medication',
        ]);
    });
});

describe('consistency', function () {
    it('gives two different people different targets', function () {
        $a = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose));
        $b = macroCalc(macroInput(Sex::Female, 45, 160, 95, ActivityLevel::Sedentary, WeightGoal::Lose));

        expect($a->targets->calories)->not->toEqual($b->targets->calories)
            ->and($a->targets->protein)->not->toEqual($b->targets->protein)
            ->and($a->targets->fat)->not->toEqual($b->targets->fat)
            ->and($a->roundedTargets())->not->toEqual($b->roundedTargets());
    });

    it('reacts to every single input', function () {
        $base = macroCalc(macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose))->targets;

        $variants = [
            macroInput(Sex::Female, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose),
            macroInput(Sex::Male, 31, 175, 85, ActivityLevel::Light, WeightGoal::Lose),
            macroInput(Sex::Male, 30, 176, 85, ActivityLevel::Light, WeightGoal::Lose),
            macroInput(Sex::Male, 30, 175, 86, ActivityLevel::Light, WeightGoal::Lose),
            macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Moderate, WeightGoal::Lose),
            macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Maintain),
            macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose, DietApproach::StrictKeto),
        ];

        foreach ($variants as $variant) {
            expect(macroCalc($variant)->targets)->not->toEqual($base);
        }
    });

    it('is deterministic', function () {
        $input = macroInput(Sex::Female, 45, 160, 95, ActivityLevel::Sedentary, WeightGoal::Lose, DietApproach::StrictKeto, 70);

        $first = (new MacroCalculator)->calculate($input);
        $second = (new MacroCalculator)->calculate($input);

        expect($second)->toEqual($first)
            ->and($second->targets->toArray())->toBe($first->targets->toArray())
            ->and($second->warnings)->toBe($first->warnings);
    });

    it('makes the macros add up to the calorie target exactly', function () {
        foreach (DietApproach::cases() as $approach) {
            foreach (WeightGoal::cases() as $goal) {
                $t = macroCalc(macroInput(Sex::Female, 38, 168, 74, ActivityLevel::Moderate, $goal, $approach))->targets;

                expect($t->protein * 4 + $t->netCarbs * 4 + $t->fat * 9)->toEqualWithDelta($t->calories, MACRO_DELTA)
                    ->and($t->carbs)->toBeGreaterThan($t->netCarbs);
            }
        }
    });

    it('rounds calories to the nearest 10 and grams to the nearest 1 without touching the full-precision targets', function () {
        $result = new MacroResult(
            targets: new MacroTargets(calories: 1984.9, protein: 133.5, fat: 149.49, carbs: 52.5, netCarbs: 25.0),
            bmr: 1700, tdee: 2400, calorieAdjustment: -415.1, proteinPerKg: 1.7, referenceWeightKg: 78.5,
            warnings: [], requiresMedicalAdvice: false, bmi: 27.0, calorieFloor: 1700,
        );

        expect($result->roundedTargets()->toArray())->toBe([
            'calories' => 1980.0, 'protein_g' => 134.0, 'fat_g' => 149.0, 'carbs_g' => 53.0, 'net_carbs_g' => 25.0,
        ])->and($result->targets->calories)->toBe(1984.9);
    });

    it('rejects impossible body data', function () {
        expect(fn () => macroInput(Sex::Male, 0, 175, 85, ActivityLevel::Light, WeightGoal::Lose))->toThrow(InvalidArgumentException::class)
            ->and(fn () => macroInput(Sex::Male, 30, 0, 85, ActivityLevel::Light, WeightGoal::Lose))->toThrow(InvalidArgumentException::class)
            ->and(fn () => macroInput(Sex::Male, 30, 175, -1, ActivityLevel::Light, WeightGoal::Lose))->toThrow(InvalidArgumentException::class)
            ->and(fn () => macroInput(Sex::Male, 30, 175, 85, ActivityLevel::Light, WeightGoal::Lose, goalWeight: 0))->toThrow(InvalidArgumentException::class);
    });
});

describe('translations', function () {
    it('has an Arabic and an English string for every warning and condition key', function (string $locale) {
        $lines = require dirname(__DIR__, 3)."/lang/{$locale}/macros.php";

        $keys = [
            ...array_values((new ReflectionClass(MacroWarning::class))->getConstants()),
            ...MacroCalculator::medicalConditions(),
        ];

        foreach ($keys as $key) {
            [$file, $group, $item] = explode('.', $key);

            expect($file)->toBe('macros')
                ->and($lines[$group][$item] ?? null)->toBeString()->not->toBeEmpty();
        }

        expect($lines['disclaimer'])->toBeString()->not->toBeEmpty();
    })->with(['ar', 'en']);

    it('keeps both locales in sync', function () {
        $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($lines as $key => $value) {
                $keys = is_array($value)
                    ? [...$keys, ...$flatten($value, $prefix.$key.'.')]
                    : [...$keys, $prefix.$key];
            }

            return $keys;
        };

        $ar = $flatten(require dirname(__DIR__, 3).'/lang/ar/macros.php');
        $en = $flatten(require dirname(__DIR__, 3).'/lang/en/macros.php');

        expect($ar)->toEqualCanonicalizing($en);
    });
});
