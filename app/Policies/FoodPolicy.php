<?php

namespace App\Policies;

use App\Models\Food;
use App\Models\User;

class FoodPolicy
{
    /** Anyone may browse the catalogue (search itself is scoped with Food::visibleTo()). */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /** Catalogue foods are public; a custom food is visible to its owner only. */
    public function view(?User $user, Food $food): bool
    {
        return ! $food->isCustom() || ($user !== null && (int) $food->user_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Only the owner of a custom food may change it. Catalogue rows are never editable. */
    public function update(User $user, Food $food): bool
    {
        return $food->isCustom() && (int) $food->user_id === (int) $user->id;
    }

    public function delete(User $user, Food $food): bool
    {
        return $this->update($user, $food);
    }
}
