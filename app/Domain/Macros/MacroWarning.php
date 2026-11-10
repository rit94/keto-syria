<?php

namespace App\Domain\Macros;

/**
 * Translation keys the calculator can attach to a result.
 * Each one resolves through __() to lang/{ar,en}/macros.php.
 */
final class MacroWarning
{
    /** Under 18: no deficit is calculated; needs a doctor/dietitian. */
    public const string AGE_UNDER_18 = 'macros.warnings.age_under_18';

    /** 65 and over: muscle preservation and medication review matter more. */
    public const string AGE_65_PLUS = 'macros.warnings.age_65_plus';

    /** Current BMI below 18.5: no deficit is ever applied. */
    public const string BMI_UNDERWEIGHT = 'macros.warnings.bmi_underweight';

    /** Current BMI of 40 or more: supervised weight loss recommended. */
    public const string BMI_40_PLUS = 'macros.warnings.bmi_40_plus';

    /** The calorie target was raised to the safety floor. */
    public const string CALORIE_FLOOR_APPLIED = 'macros.warnings.calorie_floor_applied';

    /** The goal weight means losing more than a quarter of current body weight. */
    public const string AGGRESSIVE_GOAL = 'macros.warnings.aggressive_goal';

    /** The goal weight itself would be underweight (BMI < 18.5). */
    public const string GOAL_WEIGHT_UNDERWEIGHT = 'macros.warnings.goal_weight_underweight';

    /** Goal says "lose" but goal weight is not below current weight (or the reverse for "gain"). */
    public const string GOAL_WEIGHT_MISMATCH = 'macros.warnings.goal_weight_mismatch';

    /** Protein was limited to the maximum share of calories. */
    public const string PROTEIN_LIMITED = 'macros.warnings.protein_limited';

    /** Fat ended up below half of the calories – unusually low for a keto plan. */
    public const string LOW_FAT = 'macros.warnings.low_fat';
}
