<?php

namespace App\Services\Ai\Tools;

use App\Enums\Meal;
use App\Services\Ai\Proposals\ProposalType;

class ProposeMealPlanTool extends AbstractProposalTool
{
    public function name(): string
    {
        return 'propose_meal_plan';
    }

    public function description(): string
    {
        return 'Prepare a meal plan for one or more days. This does NOT save anything: it creates a pending proposal with the planned nutrition of '
            .'each day compared with the user\'s targets, which the user must confirm. Each item is a database food (food_id) or one of the user\'s '
            .'recipes (recipe_id), never both, with a quantity and unit (recipes: "serving" or "g"). Check the returned day totals against the targets '
            .'and adjust the plan before presenting it.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'name' => ['type' => 'string'],
            'starts_on' => ['type' => 'string', 'description' => 'YYYY-MM-DD, "today" or "tomorrow".'],
            'days' => ['type' => 'integer', 'description' => 'Number of days the plan covers, up to a month.'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'planned_on' => ['type' => 'string', 'description' => 'YYYY-MM-DD within the plan period.'],
                        'meal' => ['type' => 'string', 'enum' => Meal::values()],
                        'food_id' => ['type' => 'integer'],
                        'recipe_id' => ['type' => 'integer'],
                        'quantity' => ['type' => 'number'],
                        'unit' => ['type' => 'string'],
                        'note' => ['type' => 'string'],
                    ],
                    'required' => ['planned_on', 'meal', 'quantity', 'unit'],
                    'additionalProperties' => false,
                ],
            ],
        ], ['name', 'starts_on', 'days', 'items']);
    }

    protected function rules(): array
    {
        return [
            'name' => ['present'],
            'starts_on' => ['present'],
            'days' => ['present'],
            'items' => ['present', 'array'],
        ];
    }

    protected function type(): ProposalType
    {
        return ProposalType::MealPlan;
    }
}
