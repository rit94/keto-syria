<?php

namespace App\Livewire\Foods\Support;

use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\LoggingException;
use App\View\Format;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns the exceptions of the food / logging services into one friendly, translated sentence
 * for inline errors and toasts. Anything unexpected is re-thrown – never swallowed.
 */
trait NutritionErrors
{
    /** Exceptions the UI knows how to explain. */
    protected function isNutritionError(Throwable $e): bool
    {
        return $e instanceof UnknownUnitException
            || $e instanceof InvalidQuantityException
            || $e instanceof LoggingException
            || $e instanceof AuthorizationException
            || $e instanceof ValidationException;
    }

    protected function nutritionError(Throwable $e): string
    {
        return match (true) {
            $e instanceof UnknownUnitException => __('nutrition_errors.unknown_unit', ['unit' => $e->unit]),
            $e instanceof InvalidQuantityException => __('nutrition_errors.invalid_quantity'),
            $e instanceof LoggingException => __('nutrition_errors.'.$e->reason),
            $e instanceof AuthorizationException => __('foods.errors.not_accessible'),
            $e instanceof ValidationException => collect($e->errors())->flatten()->first() ?? __('common.errors.generic'),
            default => throw $e,
        };
    }

    /** Typed quantity ("١٫٥", "1,5", "") → float, or null when it is not a number. */
    protected function parseQuantity(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $text = str_replace(',', '.', Format::westernDigits(trim((string) $value)));

        return is_numeric($text) && is_finite((float) $text) ? (float) $text : null;
    }
}
