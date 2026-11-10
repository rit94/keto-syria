<?php

namespace App\Services\Ai\Tools;

use App\Enums\Meal;
use App\Services\Ai\Proposals\ProposalType;

class ProposeLogMealTool extends AbstractProposalTool
{
    public function name(): string
    {
        return 'propose_log_meal';
    }

    public function description(): string
    {
        return 'Prepare logging one meal (one or more foods) to the user\'s diary. This does NOT log anything: it creates a pending proposal with the '
            .'calculated nutrition that the user must confirm in the app. Call it when the user says they ate something or asks you to log it, once you '
            .'know each food (ids from search_foods), its quantity and unit, and whether a weight was raw or cooked when that matters. '
            .'Log cooking fat (oil, ghee, butter) as its own item. Returns the proposal id and the engine-computed preview.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, "today" or "yesterday". Defaults to today.'],
            'meal' => ['type' => 'string', 'enum' => Meal::values()],
            'items' => ['type' => 'array', 'items' => self::foodAmountSchema()],
        ], ['meal', 'items']);
    }

    protected function rules(): array
    {
        // structure only – ProposalPreviewBuilder validates the values and the ids
        return [
            'date' => ['sometimes', 'nullable', 'string'],
            'meal' => ['present'],
            'items' => ['present', 'array'],
        ];
    }

    protected function type(): ProposalType
    {
        return ProposalType::LogMeal;
    }
}
