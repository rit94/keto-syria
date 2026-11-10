<?php

namespace App\Policies;

use App\Models\FoodLogEntry;
use App\Models\User;

/** Owner-only. */
class FoodLogEntryPolicy
{
    public function view(User $user, FoodLogEntry $entry): bool
    {
        return $this->owns($user, $entry);
    }

    public function update(User $user, FoodLogEntry $entry): bool
    {
        return $this->owns($user, $entry);
    }

    public function delete(User $user, FoodLogEntry $entry): bool
    {
        return $this->owns($user, $entry);
    }

    private function owns(User $user, FoodLogEntry $entry): bool
    {
        return (int) $entry->user_id === (int) $user->id;
    }
}
