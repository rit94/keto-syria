<?php

namespace App\Livewire\Foods\Support;

use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Recipe;
use App\Services\Food\UnitResolver;

/** Human label for a stored unit key, in the current locale. Display only. */
final class UnitLabels
{
    public static function for(?Food $food, string $unit): string
    {
        $key = UnitResolver::normalize($unit);

        if (isset(UnitResolver::UNIVERSAL[$key]) || $key === 'serving') {
            return __('units.'.$key);
        }

        return $food?->units->firstWhere('unit', $key)?->label() ?: $unit;
    }

    public static function forEntry(FoodLogEntry $entry): string
    {
        return self::for($entry->food_id !== null ? $entry->food : null, $entry->unit);
    }

    /**
     * Units an entry may be switched to: every unit of its food, 'serving' / 'g' for a recipe,
     * and only its current unit once the source was deleted (the service refuses a change then).
     *
     * @return array<string, string> unit key => label
     */
    public static function optionsForEntry(FoodLogEntry $entry, UnitResolver $units): array
    {
        if ($entry->food_id !== null && $entry->food instanceof Food) {
            return self::options($entry->food, $units);
        }

        if ($entry->recipe_id !== null && $entry->recipe instanceof Recipe) {
            return ['serving' => __('units.serving'), 'g' => __('units.g')];
        }

        return [$entry->unit => self::for(null, $entry->unit)];
    }

    /** @return array<string, string> unit key => label */
    public static function options(Food $food, UnitResolver $units): array
    {
        return collect($units->unitsFor($food))->mapWithKeys(fn (array $unit) => [$unit['unit'] => $unit['label']])->all();
    }

    /**
     * The amount offered first for a food: one of its default household unit, otherwise 100 g.
     *
     * @return array{quantity: float, unit: string}
     */
    public static function defaultAmount(Food $food, UnitResolver $units): array
    {
        foreach ($units->unitsFor($food) as $unit) {
            if ($unit['is_default']) {
                return ['quantity' => 1.0, 'unit' => $unit['unit']];
            }
        }

        return ['quantity' => 100.0, 'unit' => 'g'];
    }
}
