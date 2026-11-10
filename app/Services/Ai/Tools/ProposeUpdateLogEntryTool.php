<?php

namespace App\Services\Ai\Tools;

use App\Enums\Meal;
use App\Services\Ai\Proposals\ProposalType;

class ProposeUpdateLogEntryTool extends AbstractProposalTool
{
    public function name(): string
    {
        return 'propose_update_log_entry';
    }

    public function description(): string
    {
        return 'Prepare a change to one existing diary entry (quantity, unit, meal and/or date). This does NOT change anything: it creates a pending '
            .'proposal showing the entry before and after, which the user must confirm. Get the entry_id from get_daily_summary first. '
            .'To change which food an entry is, propose deleting it and logging the right food instead.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'entry_id' => ['type' => 'integer'],
            'changes' => [
                'type' => 'object',
                'description' => 'Only the fields to change.',
                'properties' => [
                    'quantity' => ['type' => 'number'],
                    'unit' => ['type' => 'string'],
                    'meal' => ['type' => 'string', 'enum' => Meal::values()],
                    'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                ],
                'additionalProperties' => false,
            ],
        ], ['entry_id', 'changes']);
    }

    protected function rules(): array
    {
        return [
            'entry_id' => ['present'],
            'changes' => ['present', 'array'],
        ];
    }

    protected function type(): ProposalType
    {
        return ProposalType::UpdateLogEntry;
    }
}
