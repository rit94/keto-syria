<?php

namespace App\Policies;

use App\Models\MealPlan;
use App\Models\User;

/** Owner-only. Plan items are authorised through their plan. */
class MealPlanPolicy
{
    public function view(User $user, MealPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function update(User $user, MealPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function delete(User $user, MealPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    private function owns(User $user, MealPlan $plan): bool
    {
        return (int) $plan->user_id === (int) $user->id;
    }
}
