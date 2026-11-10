<?php

namespace App\Services\Ai\Coach;

use App\Models\CoachConversation;
use App\Models\CoachMessage;
use Illuminate\Support\Facades\Storage;
use stdClass;

/**
 * Turns the stored messages of a conversation into the `messages` array for the API.
 *
 * Guarantees:
 *  - Replay is append-only: stored blocks are sent back exactly as received (thinking blocks
 *    included), which keeps the prompt cache warm and the model's reasoning blocks valid.
 *  - A tool_use is never sent without its tool_result, and a tool_result never without its
 *    tool_use – whatever happened to the process between storing the two.
 *  - Long conversations are trimmed only at the start of a user turn, from a stored anchor
 *    that moves rarely (so the cached prefix survives between trims). Reasoning blocks that
 *    were produced with the untrimmed history are dropped from then on, because the API
 *    rejects reasoning replayed after its preceding history changed; text and tool calls stay.
 *
 * Trimming drops old turns; it does not write a model-generated summary of them.
 */
class ConversationHistory
{
    private const TRIM_NOTICE = SystemPrompt::APP_NOTE_PREFIX.' Earlier messages of this conversation are no longer included. '
        .'If the user refers to something you cannot see, say so and use the read tools for current data.';

    private const INTERRUPTED = '{"error":{"code":"interrupted","message":"This tool call was interrupted and did not run. Nothing was saved."}}';

    /**
     * @param  bool  $cacheBreakpoint  mark the last block so the whole conversation prefix is cached
     * @return list<array{role: string, content: list<stdClass>}>
     */
    public function messages(CoachConversation $conversation, bool $cacheBreakpoint = true): array
    {
        $stored = CoachMessage::query()
            ->where('conversation_id', $conversation->id)
            ->when($conversation->history_anchor_id, fn ($q, $anchor) => $q->where('id', '>=', $anchor))
            ->orderBy('id')
            ->get();

        $messages = [];
        if ($conversation->history_anchor_id !== null) {
            $messages[] = ['role' => 'user', 'content' => [self::text(self::TRIM_NOTICE)]];
        }

        $stripUpTo = (int) ($conversation->thinking_stripped_upto_id ?? 0);

        foreach ($stored as $message) {
            $blocks = match ($message->role) {
                CoachMessage::ROLE_ASSISTANT => $this->assistantBlocks($message, $message->id <= $stripUpTo),
                CoachMessage::ROLE_NOTE => [self::text(SystemPrompt::APP_NOTE_PREFIX.' '.$message->displayText())],
                default => $this->userBlocks($message),
            };

            if ($blocks === []) {
                continue;
            }

            $messages[] = [
                'role' => $message->role === CoachMessage::ROLE_ASSISTANT ? 'assistant' : 'user',
                'content' => $blocks,
            ];
        }

        $messages = $this->pairToolBlocks($messages);

        // the API requires the conversation to open with a user turn
        while ($messages !== [] && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        if ($cacheBreakpoint && $messages !== []) {
            $last = array_key_last($messages);
            if ($messages[$last]['role'] === 'user') {
                $index = array_key_last($messages[$last]['content']);
                $block = clone $messages[$last]['content'][$index];
                $block->cache_control = (object) ['type' => 'ephemeral'];
                $messages[$last]['content'][$index] = $block;
            }
        }

        return $messages;
    }

    /**
     * Move the replay window forward when the conversation has grown past the configured size.
     * Call only between turns (before storing a new user message).
     */
    public function trimIfNeeded(CoachConversation $conversation): bool
    {
        $max = max(4, (int) config('ai.coach.max_history_messages', 80));
        $keep = max(2, min($max, (int) config('ai.coach.keep_history_messages', 40)));

        $window = CoachMessage::query()
            ->where('conversation_id', $conversation->id)
            ->when($conversation->history_anchor_id, fn ($q, $anchor) => $q->where('id', '>=', $anchor))
            ->orderBy('id')
            ->get(['id', 'role', 'meta']);

        if ($window->count() <= $max) {
            return false;
        }

        // Candidate anchors are the starts of user turns; pick the earliest that leaves at most $keep
        // messages, or failing that the most recent turn start.
        $total = $window->count();
        $anchor = null;
        foreach ($window->values() as $position => $message) {
            if ($message->kind() !== 'user' || $position === 0) {
                continue;
            }
            $anchor = $message->id;
            if ($total - $position <= $keep) {
                break;
            }
        }

        if ($anchor === null) {
            return false;
        }

        $conversation->forceFill([
            'history_anchor_id' => $anchor,
            'thinking_stripped_upto_id' => $window->last()->id,
        ])->save();

        return true;
    }

    /** @return list<stdClass> */
    private function assistantBlocks(CoachMessage $message, bool $stripThinking): array
    {
        $blocks = $message->rawBlocks();

        if ($stripThinking) {
            $blocks = array_values(array_filter(
                $blocks,
                fn (stdClass $block) => ! in_array($block->type ?? null, ['thinking', 'redacted_thinking'], true),
            ));
        }

        return $blocks;
    }

    /** @return list<stdClass> */
    private function userBlocks(CoachMessage $message): array
    {
        $blocks = [];

        foreach ($message->rawBlocks() as $block) {
            // images are stored on the private disk and inlined (same bytes every time) on replay
            if (($block->type ?? null) === 'image' && ($block->source->type ?? null) === 'stored') {
                $blocks[] = $this->inlineImage($block);

                continue;
            }
            $blocks[] = $block;
        }

        return $blocks;
    }

    private function inlineImage(stdClass $block): stdClass
    {
        $disk = Storage::disk((string) ($block->source->disk ?? config('ai.images.disk', 'local')));
        $path = (string) ($block->source->path ?? '');

        if ($path === '' || ! $disk->exists($path)) {
            return self::text('[image no longer available]');
        }

        return (object) [
            'type' => 'image',
            'source' => (object) [
                'type' => 'base64',
                'media_type' => (string) ($block->source->media_type ?? 'image/jpeg'),
                'data' => base64_encode($disk->get($path)),
            ],
        ];
    }

    /**
     * Make every assistant tool_use be answered by a tool_result in the very next user message,
     * and drop tool_results that answer nothing.
     *
     * @param  list<array{role: string, content: list<stdClass>}>  $messages
     * @return list<array{role: string, content: list<stdClass>}>
     */
    private function pairToolBlocks(array $messages): array
    {
        $paired = [];
        $pending = [];   // tool_use ids of the previous assistant message still waiting for a result

        foreach ($messages as $message) {
            if ($message['role'] === 'assistant') {
                if ($pending !== []) {
                    $paired[] = ['role' => 'user', 'content' => $this->interrupted($pending)];
                }
                $pending = [];
                foreach ($message['content'] as $block) {
                    if (($block->type ?? null) === 'tool_use' && is_string($block->id ?? null)) {
                        $pending[] = $block->id;
                    }
                }
                $paired[] = $message;

                continue;
            }

            $results = [];
            $others = [];
            foreach ($message['content'] as $block) {
                if (($block->type ?? null) !== 'tool_result') {
                    $others[] = $block;
                } elseif (in_array($block->tool_use_id ?? null, $pending, true)) {
                    $results[] = $block;
                    $pending = array_values(array_diff($pending, [$block->tool_use_id]));
                }
                // a tool_result without a matching tool_use is dropped
            }

            // tool results must come first in the user message that follows the tool calls
            $content = [...$results, ...($pending === [] ? [] : $this->interrupted($pending)), ...$others];
            $pending = [];

            if ($content !== []) {
                $paired[] = ['role' => 'user', 'content' => $content];
            }
        }

        if ($pending !== []) {
            $paired[] = ['role' => 'user', 'content' => $this->interrupted($pending)];
        }

        return $paired;
    }

    /**
     * @param  list<string>  $toolUseIds
     * @return list<stdClass>
     */
    private function interrupted(array $toolUseIds): array
    {
        return array_map(fn (string $id) => (object) [
            'type' => 'tool_result',
            'tool_use_id' => $id,
            'content' => self::INTERRUPTED,
            'is_error' => true,
        ], $toolUseIds);
    }

    private static function text(string $text): stdClass
    {
        return (object) ['type' => 'text', 'text' => $text];
    }
}
