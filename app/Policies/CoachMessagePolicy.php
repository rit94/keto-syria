<?php

namespace App\Policies;

use App\Models\CoachMessage;
use App\Models\User;

/** Owner-only, through the message's conversation. Messages are never edited. */
class CoachMessagePolicy
{
    public function view(User $user, CoachMessage $message): bool
    {
        return (int) $message->conversation?->user_id === (int) $user->id;
    }

    public function update(User $user, CoachMessage $message): bool
    {
        return false;
    }

    public function delete(User $user, CoachMessage $message): bool
    {
        return false;
    }
}
