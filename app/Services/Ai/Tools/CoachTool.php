<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;

/**
 * One capability the coach model can call. Tools are the model's only source of facts and
 * numbers, and they never trust the model: input is validated server-side and every id is
 * re-checked against `$user`.
 */
interface CoachTool
{
    /** Stable snake_case name sent to the API. */
    public function name(): string;

    /** Model-facing description: what it returns and when to call it. */
    public function description(): string;

    /** @return array<string, mixed> JSON Schema of the input object */
    public function inputSchema(): array;

    /**
     * @param  array<string, mixed>  $input  raw model input (untrusted)
     * @param  ?CoachConversation  $conversation  the chat this call belongs to (proposals attach to it)
     * @return array<string, mixed> compact JSON-serialisable result; a top-level `error` key marks a failed call
     */
    public function handle(User $user, array $input, ?CoachConversation $conversation = null): array;
}
