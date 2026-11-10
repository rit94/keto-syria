<?php

namespace App\Livewire\Profile\Concerns;

use App\Domain\Nutrition\MacroTargets;
use App\Domain\Nutrition\Nutrients;

/**
 * Manual daily-target form state shared by onboarding and the profile page.
 *
 * Property names match ProfileService's $customTargets keys so its validation
 * errors (safety floor, macro/calorie mismatch) show under the right inputs.
 * Nothing here decides whether targets are acceptable — ProfileService does.
 */
trait CustomTargetFields
{
    /** @var string|int|float|null */
    public $calories = null;

    /** @var string|int|float|null */
    public $protein_g = null;

    /** @var string|int|float|null */
    public $fat_g = null;

    /** @var string|int|float|null */
    public $net_carbs_g = null;

    /** @var string|int|float|null */
    public $carbs_g = null;

    /** @return list<string> */
    public static function customTargetFieldNames(): array
    {
        return ['calories', 'protein_g', 'fat_g', 'net_carbs_g', 'carbs_g'];
    }

    protected function fillCustomTargets(MacroTargets $targets): void
    {
        $this->calories = $targets->calories;
        $this->protein_g = $targets->protein;
        $this->fat_g = $targets->fat;
        $this->net_carbs_g = $targets->netCarbs;
        $this->carbs_g = $targets->carbs;
    }

    /**
     * The $customTargets array for ProfileService::save().
     *
     * @return array<string, mixed>
     */
    protected function customTargets(): array
    {
        $values = [];

        foreach (self::customTargetFieldNames() as $field) {
            $value = $this->{$field};
            $values[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        return $values;
    }

    /**
     * Energy implied by the typed macro grams, from the nutrition engine (Atwater 4/4/9).
     * Null while a field is empty or not a number. Display only — the service validates.
     */
    protected function customMacroCalories(): ?float
    {
        foreach (['protein_g', 'fat_g', 'net_carbs_g'] as $field) {
            if (! is_numeric($this->{$field}) || (float) $this->{$field} < 0) {
                return null;
            }
        }

        return (new Nutrients(
            calories: 0.0,
            protein: (float) $this->protein_g,
            carbs: (float) $this->net_carbs_g,
            fiber: 0.0,
            fat: (float) $this->fat_g,
        ))->atwaterCalories();
    }
}
