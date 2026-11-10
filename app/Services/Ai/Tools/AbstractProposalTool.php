<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;
use App\Services\Ai\Proposals\ProposalService;
use App\Services\Ai\Proposals\ProposalType;

/**
 * Base of the "write" tools. They never write: they store a pending CoachProposal with an
 * engine-computed preview and tell the model, in so many words, that nothing is saved yet.
 */
abstract class AbstractProposalTool extends AbstractTool
{
    public const NOT_SAVED_NOTE = 'NOTHING has been saved or changed yet. The app now shows this proposal to the user as a card they can edit, '
        .'confirm or reject. Do not say it was logged, saved, updated or deleted. Briefly tell the user what you prepared and that it is waiting '
        .'for their confirmation. You will receive an app note with the real outcome once they decide.';

    public function __construct(protected readonly ProposalService $proposals) {}

    abstract protected function type(): ProposalType;

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $proposal = $this->proposals->propose($user, $conversation, $this->type(), $data);

        return [
            'proposal_id' => $proposal->id,
            'status' => 'pending_user_confirmation',
            'saved' => false,
            'note' => self::NOT_SAVED_NOTE,
            'expires_at' => $proposal->expires_at->toIso8601String(),
            'preview' => $proposal->preview,
        ];
    }
}
