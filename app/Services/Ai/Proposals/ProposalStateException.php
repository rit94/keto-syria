<?php

namespace App\Services\Ai\Proposals;

use App\Services\Ai\Exceptions\AiException;

/** The proposal can no longer be confirmed / rejected: already resolved, or expired. */
class ProposalStateException extends AiException
{
    public const ALREADY_RESOLVED = 'already_resolved';

    public const EXPIRED = 'expired';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(__('coach.proposals.errors.'.$reason));
    }
}
