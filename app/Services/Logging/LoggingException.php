<?php

namespace App\Services\Logging;

use DomainException;

/**
 * A log request that is well-formed but cannot be calculated honestly from the stored facts.
 * `$reason` is a stable machine key; the message is already translated.
 */
class LoggingException extends DomainException
{
    public const NO_COOKED_YIELD = 'no_cooked_yield';

    public const RECIPE_HAS_NO_WEIGHT = 'recipe_has_no_weight';

    public const SOURCE_DELETED = 'source_deleted';

    public const PLAN_ITEM_EMPTY = 'plan_item_empty';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(__('nutrition_errors.'.$reason));
    }

    /** Cooked weight given for a raw food that has no cooked_yield – we never guess a yield. */
    public static function noCookedYield(): self
    {
        return new self(self::NO_COOKED_YIELD);
    }

    /** Recipe logged by grams but it has neither a cooked weight nor any ingredient weight. */
    public static function recipeHasNoWeight(): self
    {
        return new self(self::RECIPE_HAS_NO_WEIGHT);
    }

    /** The entry's food/recipe was deleted and the requested change needs its facts. */
    public static function sourceDeleted(): self
    {
        return new self(self::SOURCE_DELETED);
    }

    /** A meal-plan item whose food/recipe was deleted cannot be logged. */
    public static function planItemEmpty(): self
    {
        return new self(self::PLAN_ITEM_EMPTY);
    }
}
