<?php

namespace App\Services\Food;

use InvalidArgumentException;

/**
 * The unit is neither a universal mass unit nor a household unit defined for the food
 * (or, for recipes, neither 'serving' nor 'g').
 */
class UnknownUnitException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $unit,
        public readonly ?int $foodId = null,
    ) {
        parent::__construct(__('nutrition_errors.unknown_unit', ['unit' => $unit]));
    }
}
