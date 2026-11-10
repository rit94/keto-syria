<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Proposals\ProposalType;

class ProposeDeleteLogEntryTool extends AbstractProposalTool
{
    public function name(): string
    {
        return 'propose_delete_log_entry';
    }

    public function description(): string
    {
        return 'Prepare deleting one diary entry. This does NOT delete anything: it creates a pending proposal showing the entry, which the user '
            .'must confirm. Get the entry_id from get_daily_summary first.';
    }

    public function inputSchema(): array
    {
        return self::schema(['entry_id' => ['type' => 'integer']], ['entry_id']);
    }

    protected function rules(): array
    {
        return ['entry_id' => ['present']];
    }

    protected function type(): ProposalType
    {
        return ProposalType::DeleteLogEntry;
    }
}
