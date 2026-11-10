<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

/** Owner-only. */
class RecipePolicy
{
    public function view(User $user, Recipe $recipe): bool
    {
        return $this->owns($user, $recipe);
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $this->owns($user, $recipe);
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $this->owns($user, $recipe);
    }

    private function owns(User $user, Recipe $recipe): bool
    {
        return (int) $recipe->user_id === (int) $user->id;
    }
}
