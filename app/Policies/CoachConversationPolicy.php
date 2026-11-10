<?php

namespace App\Policies;

use App\Models\CoachConversation;
use App\Models\User;

/** Owner-only. */
class CoachConversationPolicy
{
    public function view(User $user, CoachConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function update(User $user, CoachConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function delete(User $user, CoachConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    private function owns(User $user, CoachConversation $conversation): bool
    {
        return (int) $conversation->user_id === (int) $user->id;
    }
}
