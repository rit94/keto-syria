<?php

namespace App\Services\Food;

use InvalidArgumentException;

/** A quantity that is not a finite number greater than zero. */
class InvalidQuantityException extends InvalidArgumentException
{
    public function __construct(public readonly float $quantity)
    {
        parent::__construct(__('nutrition_errors.invalid_quantity'));
    }

    /** @throws self */
    public static function assertValid(float $quantity): void
    {
        if (! is_finite($quantity) || $quantity <= 0) {
            throw new self($quantity);
        }
    }
}
