<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Proposals\ProposalType;

class ProposeSaveRecipeTool extends AbstractProposalTool
{
    public function name(): string
    {
        return 'propose_save_recipe';
    }

    public function description(): string
    {
        return 'Prepare saving a new recipe to the user\'s recipes. This does NOT save anything: it creates a pending proposal with the nutrition '
            .'calculated from the ingredients (whole recipe and per serving), which the user must confirm. Every ingredient must be a food from the '
            .'database (ids from search_foods) with a quantity and unit, including cooking fat. Give cooked_weight_g only if the user weighed the finished dish.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'name' => ['type' => 'string'],
            'description' => ['type' => 'string', 'description' => 'Optional one-line description.'],
            'instructions' => ['type' => 'string', 'description' => 'Preparation steps, in the user\'s language.'],
            'servings' => ['type' => 'number'],
            'cooked_weight_g' => ['type' => 'number', 'description' => 'Measured weight of the finished dish in grams, if known.'],
            'ingredients' => ['type' => 'array', 'items' => self::foodAmountSchema(false)],
        ], ['name', 'servings', 'ingredients']);
    }

    protected function rules(): array
    {
        return [
            'name' => ['present'],
            'description' => ['sometimes', 'nullable'],
            'instructions' => ['sometimes', 'nullable'],
            'servings' => ['present'],
            'cooked_weight_g' => ['sometimes', 'nullable'],
            'ingredients' => ['present', 'array'],
        ];
    }

    protected function type(): ProposalType
    {
        return ProposalType::SaveRecipe;
    }
}
