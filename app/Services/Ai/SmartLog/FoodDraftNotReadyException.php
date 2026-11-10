<?php

namespace App\Services\Ai\SmartLog;

use App\Services\Ai\Exceptions\AiException;

/**
 * commit() was called on a draft that still has open points. `$draft` is the freshly
 * recalculated draft, so the form can be re-rendered with the current statuses.
 */
class FoodDraftNotReadyException extends AiException
{
    public const EMPTY = 'empty';

    public const NO_MEAL = 'no_meal';

    public const UNRESOLVED_ITEMS = 'unresolved_items';

    /**
     * @param  list<string>  $itemKeys  keys of the rows that block the commit
     */
    public function __construct(
        public readonly string $reason,
        public readonly FoodDraft $draft,
        public readonly array $itemKeys = [],
    ) {
        parent::__construct(__('coach.draft.errors.'.$reason));
    }
}
