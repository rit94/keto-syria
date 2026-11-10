<?php

namespace App\Policies;

use App\Models\CoachProposal;
use App\Models\User;

/** Owner-only. `update` covers confirming and rejecting. */
class CoachProposalPolicy
{
    public function view(User $user, CoachProposal $proposal): bool
    {
        return $this->owns($user, $proposal);
    }

    public function update(User $user, CoachProposal $proposal): bool
    {
        return $this->owns($user, $proposal);
    }

    public function delete(User $user, CoachProposal $proposal): bool
    {
        return $this->owns($user, $proposal);
    }

    private function owns(User $user, CoachProposal $proposal): bool
    {
        return (int) $proposal->user_id === (int) $user->id;
    }
}
