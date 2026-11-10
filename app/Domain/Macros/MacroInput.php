<?php

namespace App\Domain\Macros;

use App\Enums\ActivityLevel;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;
use InvalidArgumentException;

/**
 * Everything the macro calculator needs to know about one person.
 *
 * Deliberately contains no diagnosis or medication data: medical conditions are
 * self-declared in the UI (see MacroCalculator::medicalConditions()) and never
 * change the arithmetic – they only gate the result behind "talk to your doctor".
 */
final readonly class MacroInput
{
    public function __construct(
        public Sex $sex,
        public int $ageYears,
        public float $heightCm,
        public float $weightKg,
        public ?float $goalWeightKg,
        public ActivityLevel $activityLevel,
        public WeightGoal $goal,
        public DietApproach $approach,
    ) {
        if ($ageYears <= 0) {
            throw new InvalidArgumentException('Age must be positive.');
        }
        if ($heightCm <= 0.0 || $weightKg <= 0.0) {
            throw new InvalidArgumentException('Height and weight must be positive.');
        }
        if ($goalWeightKg !== null && $goalWeightKg <= 0.0) {
            throw new InvalidArgumentException('Goal weight must be positive when given.');
        }
    }
}
