<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeightEntry;

/** Weigh-ins are private: only their owner may see or change them. */
class WeightEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // lists are always scoped to the signed-in user's own entries
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, WeightEntry $entry): bool
    {
        return $this->owns($user, $entry);
    }

    public function update(User $user, WeightEntry $entry): bool
    {
        return $this->owns($user, $entry);
    }

    public function delete(User $user, WeightEntry $entry): bool
    {
        return $this->owns($user, $entry);
    }

    private function owns(User $user, WeightEntry $entry): bool
    {
        return (int) $entry->user_id === (int) $user->id;
    }
}
