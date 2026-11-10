<?php

namespace App\Services\Ai\Coach;

use App\Models\CoachProposal;
use Illuminate\Support\Collection;

/**
 * What one CoachService::send() produced.
 *
 * text       the assistant's reply to show (already stored as a CoachMessage)
 * proposals  pending CoachProposal models created during this turn – render each as a card
 *            with edit / confirm / reject (ProposalService). Nothing in them is saved yet.
 * uiActions  things the UI should offer, e.g. ['type' => 'open_custom_food_form', 'name' => 'شنكليش']
 * usage      token counts summed over the model calls of this turn
 * truncated  the reply was cut short (iteration guard or output limit) – `text` says so
 * refused    the model declined the request – `text` is the translated explanation
 */
final readonly class CoachReply
{
    /**
     * @param  Collection<int, CoachProposal>  $proposals
     * @param  list<array<string, mixed>>  $uiActions
     * @param  array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int}  $usage
     */
    public function __construct(
        public string $text,
        public Collection $proposals,
        public array $uiActions,
        public array $usage,
        public int $messageId,
        public int $iterations = 1,
        public bool $truncated = false,
        public bool $refused = false,
    ) {}

    /** @return array<string, mixed> ids and scalars only – safe for Livewire state / JSON */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'proposal_ids' => $this->proposals->pluck('id')->all(),
            'ui_actions' => $this->uiActions,
            'usage' => $this->usage,
            'message_id' => $this->messageId,
            'iterations' => $this->iterations,
            'truncated' => $this->truncated,
            'refused' => $this->refused,
        ];
    }
}
