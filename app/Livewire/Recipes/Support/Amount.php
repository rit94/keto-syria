<?php

namespace App\Livewire\Recipes\Support;

use App\Models\Food;
use App\Services\Food\UnitResolver;
use App\View\Format;

/**
 * Small input / display helpers shared by the recipe and meal-plan screens.
 * Quantities and units only — nutrition always comes from the engine.
 */
class Amount
{
    /** A typed quantity (may carry Arabic-Indic digits or a decimal comma) as a positive float, or null. */
    public static function parse(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim(Format::westernDigits($value)));
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) && $number > 0 ? $number : null;
    }

    /** Display label of a unit key: the food's own household unit, a universal unit, or 'serving'. */
    public static function unitLabel(?Food $food, ?string $unit): string
    {
        $unit = UnitResolver::normalize($unit ?? 'g');

        if ($food !== null) {
            foreach (app(UnitResolver::class)->unitsFor($food) as $option) {
                if ($option['unit'] === $unit) {
                    return $option['label'];
                }
            }
        }

        $key = 'units.'.$unit;

        return trans()->has($key) ? __($key) : $unit;
    }

    /** value => label options for a select, for a food's units. */
    public static function unitOptions(Food $food): array
    {
        return collect(app(UnitResolver::class)->unitsFor($food))
            ->mapWithKeys(fn (array $unit) => [$unit['unit'] => $unit['label']])
            ->all();
    }

    /** Units a recipe can be planned / logged in. */
    public static function recipeUnitOptions(): array
    {
        return ['serving' => __('units.serving'), 'g' => __('units.g')];
    }

    /** The unit and quantity to start with when a food arrives without an amount. */
    public static function defaultFor(Food $food): array
    {
        $default = $food->units->firstWhere('is_default', true);

        return $default !== null && $default->grams > 0
            ? ['quantity' => 1.0, 'unit' => $default->unit]
            : ['quantity' => 100.0, 'unit' => 'g'];
    }
}
