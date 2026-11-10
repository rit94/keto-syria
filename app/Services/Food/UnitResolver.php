<?php

namespace App\Services\Food;

use App\Models\Food;

/**
 * Turns "a quantity of a unit of a food" into grams – the only amount the nutrition engine accepts.
 *
 * Universal mass units work for every food. Anything else (piece, cup, tbsp, slice ...) must be a
 * `food_units` row of that specific food: a "cup" has no weight until we know a cup of what.
 */
class UnitResolver
{
    /** Grams per one unit. 'ouqiya' is the Syrian market ouqiya (200 g). */
    public const UNIVERSAL = [
        'g' => 1.0,
        'kg' => 1000.0,
        'oz' => 28.349523125,
        'ouqiya' => 200.0,
    ];

    /** Canonical machine key for a unit as typed / sent by a client. */
    public static function normalize(string $unit): string
    {
        return mb_strtolower(trim($unit));
    }

    /**
     * @throws InvalidQuantityException when $quantity is not finite and > 0
     * @throws UnknownUnitException when $unit is neither universal nor defined for $food
     */
    public function toGrams(Food $food, float $quantity, string $unit): float
    {
        InvalidQuantityException::assertValid($quantity);

        $key = self::normalize($unit);

        if (isset(self::UNIVERSAL[$key])) {
            return $quantity * self::UNIVERSAL[$key];
        }

        $foodUnit = $food->units->firstWhere('unit', $key);

        if ($foodUnit === null || ! is_finite($foodUnit->grams) || $foodUnit->grams <= 0) {
            throw new UnknownUnitException($unit, $food->id);
        }

        return $quantity * $foodUnit->grams;
    }

    /**
     * Every unit the food can be measured in: universal mass units first, then the food's own
     * household units (its default unit first). Labels are in the current application locale.
     *
     * @return list<array{unit: string, label: string, grams: float, is_default: bool}>
     */
    public function unitsFor(Food $food): array
    {
        $units = [];

        foreach (self::UNIVERSAL as $unit => $grams) {
            $units[$unit] = ['unit' => $unit, 'label' => __('units.'.$unit), 'grams' => $grams, 'is_default' => false];
        }

        // deterministic: the food's default unit first, then in the order they were defined
        $foodUnits = $food->units->sortBy(fn ($unit) => [$unit->is_default ? 0 : 1, $unit->id]);

        foreach ($foodUnits as $foodUnit) {
            // a food row can never redefine a universal unit
            if (isset(self::UNIVERSAL[$foodUnit->unit]) || $foodUnit->grams <= 0) {
                continue;
            }
            $units[$foodUnit->unit] = [
                'unit' => $foodUnit->unit,
                'label' => $foodUnit->label(),
                'grams' => $foodUnit->grams,
                'is_default' => $foodUnit->is_default,
            ];
        }

        return array_values($units);
    }
}
