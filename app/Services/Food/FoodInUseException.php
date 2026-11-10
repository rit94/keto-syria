<?php

namespace App\Services\Food;

use RuntimeException;

/** Thrown when a custom food cannot be deleted because a recipe still uses it. */
class FoodInUseException extends RuntimeException
{
    public static function byRecipes(int $count): self
    {
        return new self(trans_choice('custom_food.errors.in_use', $count, ['count' => $count]));
    }
}
