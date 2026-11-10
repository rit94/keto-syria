<?php

namespace App\Services\Ai\Proposals;

enum ProposalStatus: string
{
    case Pending = 'pending';
    // claimed by a confirm() call that is executing right now
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return __('coach.proposals.status.'.$this->value);
    }
}
