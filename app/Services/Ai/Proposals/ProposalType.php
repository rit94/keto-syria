<?php

namespace App\Services\Ai\Proposals;

enum ProposalType: string
{
    case LogMeal = 'log_meal';
    case UpdateLogEntry = 'update_log_entry';
    case DeleteLogEntry = 'delete_log_entry';
    case SaveRecipe = 'save_recipe';
    case MealPlan = 'meal_plan';

    /**
     * Top-level payload keys the user may change before confirming.
     *
     * @return list<string>
     */
    public function editableKeys(): array
    {
        return match ($this) {
            self::LogMeal => ['date', 'meal', 'items'],
            self::UpdateLogEntry => ['changes'],
            self::DeleteLogEntry => [],
            self::SaveRecipe => ['name', 'description', 'instructions', 'servings', 'cooked_weight_g', 'ingredients'],
            self::MealPlan => ['name', 'starts_on', 'days', 'items'],
        };
    }
}
