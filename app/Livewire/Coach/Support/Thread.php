<?php

namespace App\Livewire\Coach\Support;

use App\Models\CoachConversation;
use App\Models\CoachMessage;
use App\Models\CoachProposal;
use Illuminate\Support\Collection;

/**
 * Turns a stored conversation into what the chat shows: visible messages only, each assistant
 * message carrying the proposals that were created during the tool calls just before it.
 */
final class Thread
{
    /**
     * @return list<array{id: int, role: string, text: string, has_image: bool, proposals: Collection<int, CoachProposal>}>
     */
    public static function build(CoachConversation $conversation): array
    {
        $messages = $conversation->messages()->get();
        $proposals = $conversation->proposals()->orderBy('id')->get()->keyBy('id');

        $entries = [];
        $carry = [];

        foreach ($messages as $message) {
            /** @var CoachMessage $message */
            if ($message->kind() === 'tool_results') {
                array_push($carry, ...self::proposalIds($message));

                continue;
            }

            if (! $message->isVisible()) {
                continue;
            }

            $isUser = $message->role === CoachMessage::ROLE_USER;
            $attached = $isUser ? [] : $carry;
            if (! $isUser) {
                $carry = [];
            }

            $entries[] = [
                'id' => $message->id,
                'role' => $isUser ? 'user' : 'assistant',
                'text' => $message->displayText(),
                'has_image' => $isUser && is_array($message->meta['image'] ?? null),
                'proposals' => collect($attached)->map(fn (int $id) => $proposals->get($id))->filter()->values(),
            ];
        }

        // Proposals whose turn never produced a visible reply are still shown, after the last message.
        if ($carry !== [] && $entries !== []) {
            $last = array_key_last($entries);
            $entries[$last]['proposals'] = $entries[$last]['proposals']
                ->concat(collect($carry)->map(fn (int $id) => $proposals->get($id))->filter())->values();
        }

        return $entries;
    }

    /** @return list<int> */
    private static function proposalIds(CoachMessage $message): array
    {
        $ids = [];

        foreach ($message->content as $block) {
            if (($block['type'] ?? null) !== 'tool_result' || ! is_string($block['content'] ?? null)) {
                continue;
            }

            $data = json_decode($block['content'], true);
            if (is_array($data) && isset($data['proposal_id']) && is_numeric($data['proposal_id'])) {
                $ids[] = (int) $data['proposal_id'];
            }
        }

        return $ids;
    }
}
