<?php

namespace App\Services\Ai\Coach;

use App\Models\CoachConversation;
use App\Models\CoachMessage;
use App\Models\CoachProposal;
use App\Models\User;
use App\Services\Ai\AiRateLimiter;
use App\Services\Ai\AiResponse;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\Exceptions\AiNotConfiguredException;
use App\Services\Ai\Exceptions\AiRateLimitException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Proposals\ProposalStatus;
use App\Services\Ai\Support\AiClock;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Logging\DailySummaryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The Keto Coach chat.
 *
 * One send() = one user message → a tool-use loop with the model → one assistant reply.
 * The model can read through tools and can only *propose* writes; see ProposalService.
 *
 * Typical UI flow:
 *   $conversation = $coach->startConversation($user);
 *   $reply = $coach->send($user, $conversation, $text, $photo);   // show $reply->text
 *   foreach ($reply->proposals as $proposal) { ... card: $proposals->confirm($user, $proposal, $edits) / reject() ... }
 *   history: $conversation->messages()->get()->filter->isVisible()   → role + displayText()
 */
class CoachService
{
    private const USAGE_KEYS = ['input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens'];

    public function __construct(
        private readonly AnthropicClient $client,
        private readonly ToolRegistry $tools,
        private readonly ConversationHistory $history,
        private readonly DailySummaryService $summaries,
        private readonly AiRateLimiter $limiter,
    ) {}

    public function startConversation(User $user, ?string $title = null): CoachConversation
    {
        return CoachConversation::create(['user_id' => $user->id, 'title' => $title]);
    }

    /**
     * @param  string  $text  what the user typed (may be empty when an image is attached)
     * @param  ?UploadedFile  $image  optional photo (jpeg / png / webp / gif); stored on the private disk
     *
     * @throws AuthorizationException the conversation belongs to someone else
     * @throws AiNotConfiguredException no API key – nothing was stored or sent
     * @throws ValidationException empty / too long message, or an unacceptable image (keys: text, image)
     * @throws AiRateLimitException the user's request budget is used up
     * @throws AiUnavailableException the model could not be reached. If it failed before any reply the
     *                                user's message is removed again, so the UI can simply let them retry.
     */
    public function send(User $user, CoachConversation $conversation, string $text, ?UploadedFile $image = null): CoachReply
    {
        if ((int) $conversation->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('coach.errors.forbidden'));
        }

        $this->client->assertConfigured();

        $text = trim($text);
        $this->validateInput($text, $image);
        $this->limiter->hit($user, 'coach');

        $this->history->trimIfNeeded($conversation);

        $userMessage = $this->storeUserMessage($user, $conversation, $text, $image);

        $usage = array_fill_keys(self::USAGE_KEYS, 0);
        $proposalIds = [];
        $uiActions = [];
        $maxIterations = max(1, (int) config('ai.coach.max_iterations', 8));
        $system = [['type' => 'text', 'text' => SystemPrompt::text(), 'cache_control' => ['type' => 'ephemeral']]];
        $tools = $this->tools->definitions();

        for ($iteration = 1; ; $iteration++) {
            try {
                $response = $this->client->messages([
                    'model' => (string) config('ai.model'),
                    'max_tokens' => (int) config('ai.coach.max_tokens', 16000),
                    'output_config' => ['effort' => (string) config('ai.coach.effort', 'medium')],
                    'system' => $system,
                    'tools' => $tools,
                    'messages' => $this->history->messages($conversation),
                ]);
            } catch (AiUnavailableException $e) {
                if ($iteration === 1) {
                    $this->discard($userMessage);
                }

                throw $e;
            }

            foreach (self::USAGE_KEYS as $key) {
                $usage[$key] += $response->usage[$key] ?? 0;
            }

            if ($response->isRefusal()) {
                $message = $this->storeSynthetic($conversation, __('coach.errors.refused'), ['reason' => 'refusal', 'category' => $response->refusalCategory]);

                return $this->reply($message, $conversation, $proposalIds, $uiActions, $usage, $iteration, refused: true);
            }

            $toolUses = $response->toolUses();

            if ($response->stopReason === 'tool_use' && $toolUses !== []) {
                $this->storeAssistant($conversation, $response);

                $limitReached = $iteration >= $maxIterations;
                $results = [];

                foreach ($toolUses as $use) {
                    if ($limitReached) {
                        // out of steps: answer the call (the pairing must stay intact) without running it
                        $outcome = ['result' => ['error' => ['code' => 'step_limit_reached', 'message' => 'Not executed: the step limit for this message was reached. Nothing was saved.']], 'is_error' => true];
                    } else {
                        $outcome = $this->tools->execute($user, $use['name'], $use['input'], $conversation);
                    }

                    if (isset($outcome['result']['proposal_id'])) {
                        $proposalIds[] = (int) $outcome['result']['proposal_id'];
                    }
                    if (is_array($outcome['result']['ui_action'] ?? null)) {
                        $uiActions[] = $outcome['result']['ui_action'];
                    }

                    $results[] = array_filter([
                        'type' => 'tool_result',
                        'tool_use_id' => $use['id'],
                        'content' => self::json($outcome['result']),
                        'is_error' => $outcome['is_error'] ?: null,
                    ], fn ($value) => $value !== null);
                }

                CoachMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => CoachMessage::ROLE_USER,
                    'content' => $results,
                    'meta' => ['kind' => 'tool_results'],
                ]);

                if ($limitReached) {
                    $message = $this->storeSynthetic($conversation, __('coach.errors.too_many_steps'), ['reason' => 'max_iterations']);

                    return $this->reply($message, $conversation, $proposalIds, $uiActions, $usage, $iteration, truncated: true);
                }

                continue;
            }

            // Final answer. A reply cut off in the middle of a tool call is not stored as it is:
            // its tool_use could never be answered.
            if ($toolUses !== []) {
                $message = $this->storeSynthetic($conversation, __('coach.errors.cut_off'), ['reason' => (string) $response->stopReason]);

                return $this->reply($message, $conversation, $proposalIds, $uiActions, $usage, $iteration, truncated: true);
            }

            $truncated = $response->stopReason === 'max_tokens';

            // A reply without any text (e.g. the output limit was spent on reasoning) is replaced by
            // an app-written message: a reasoning-only assistant turn is neither showable nor replayable.
            $message = $response->text() === ''
                ? $this->storeSynthetic($conversation, __($truncated ? 'coach.errors.cut_off' : 'coach.errors.empty_reply'), ['reason' => (string) $response->stopReason])
                : $this->storeAssistant($conversation, $response);

            return $this->reply($message, $conversation, $proposalIds, $uiActions, $usage, $iteration, truncated: $truncated);
        }
    }

    // ───────────────────────────── internals ─────────────────────────────

    private function validateInput(string $text, ?UploadedFile $image): void
    {
        $maxChars = (int) config('ai.coach.max_input_chars', 4000);

        if ($text === '' && $image === null) {
            throw ValidationException::withMessages(['text' => __('coach.errors.empty_message')]);
        }
        if (mb_strlen($text) > $maxChars) {
            throw ValidationException::withMessages(['text' => __('coach.errors.message_too_long', ['max' => $maxChars])]);
        }
        if ($image !== null) {
            ImageInput::assertAcceptable($image);
        }
    }

    private function storeUserMessage(User $user, CoachConversation $conversation, string $text, ?UploadedFile $image): CoachMessage
    {
        $blocks = [];
        $meta = ['kind' => 'user', 'text' => $text];

        if ($image !== null) {
            $disk = (string) config('ai.images.disk', 'local');
            $path = $image->store(trim((string) config('ai.images.directory', 'coach-images'), '/').'/'.$user->id, $disk);

            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages(['image' => __('coach.errors.image_invalid')]);
            }

            $mediaType = ImageInput::mediaType($image);
            $blocks[] = ['type' => 'image', 'source' => ['type' => 'stored', 'disk' => $disk, 'path' => $path, 'media_type' => $mediaType]];
            $meta['image'] = ['disk' => $disk, 'path' => $path, 'media_type' => $mediaType];
        }

        $blocks[] = ['type' => 'text', 'text' => $text === '' ? '(photo attached)' : self::defang($text)];
        $blocks[] = ['type' => 'text', 'text' => $this->contextBlock($user)];

        $message = CoachMessage::create([
            'conversation_id' => $conversation->id,
            'role' => CoachMessage::ROLE_USER,
            'content' => $blocks,
            'meta' => $meta,
        ]);

        $conversation->forceFill([
            'title' => $conversation->title ?: Str::limit($text !== '' ? $text : __('coach.photo_title'), 60),
            'updated_at' => now(),
        ])->save();

        return $message;
    }

    /**
     * Per-request facts for the model. Lives in the user message (not the system prompt) so the
     * cached prefix never changes, and is stored with the message so replays are identical.
     * The numbers are engine output from DailySummaryService.
     */
    private function contextBlock(User $user): string
    {
        $today = AiClock::today();
        $summary = $this->summaries->forDate($user, $today);
        $profile = $user->profile()->first();
        $tag = SystemPrompt::APP_CONTEXT_TAG;

        $lines = [
            "<$tag>",
            'today: '.$today->toDateString().' ('.$today->englishDayOfWeek.'), timezone '.AiClock::timezone(),
            'interface_language: '.($user->locale ?: config('app.locale', 'ar')),
            'needs_medical_supervision: '.($profile?->needs_medical_supervision ? 'true' : 'false'),
            'remaining_today: '.($summary->remaining === null
                ? 'unknown (the user has no profile / targets yet)'
                : self::json($summary->remaining->toArray())),
            "</$tag>",
        ];

        return implode("\n", $lines);
    }

    /** Stop user-typed text from imitating the app's own markers (context block, outcome notes). */
    private static function defang(string $text): string
    {
        return str_ireplace(
            [SystemPrompt::APP_NOTE_PREFIX, '<'.SystemPrompt::APP_CONTEXT_TAG, '</'.SystemPrompt::APP_CONTEXT_TAG],
            ['(app note)', '(app context', '(/app context'],
            $text,
        );
    }

    private function storeAssistant(CoachConversation $conversation, AiResponse $response): CoachMessage
    {
        return CoachMessage::create([
            'conversation_id' => $conversation->id,
            'role' => CoachMessage::ROLE_ASSISTANT,
            'content' => $response->content,
            'meta' => ['kind' => 'assistant'] + $response->toArray(),
        ]);
    }

    /** An assistant message written by the app itself (limit reached, cut-off, refusal). */
    private function storeSynthetic(CoachConversation $conversation, string $text, array $meta): CoachMessage
    {
        return CoachMessage::create([
            'conversation_id' => $conversation->id,
            'role' => CoachMessage::ROLE_ASSISTANT,
            'content' => [['type' => 'text', 'text' => $text]],
            'meta' => ['kind' => 'synthetic'] + $meta,
        ]);
    }

    /** Undo a user message whose turn never got an answer. */
    private function discard(CoachMessage $message): void
    {
        try {
            $image = $message->meta['image'] ?? null;
            if (is_array($image)) {
                Storage::disk((string) $image['disk'])->delete((string) $image['path']);
            }
            $message->delete();
        } catch (Throwable) {
            // best effort – the history builder copes with an unanswered user message
        }
    }

    /**
     * @param  list<int>  $proposalIds
     * @param  list<array<string, mixed>>  $uiActions
     * @param  array<string, int>  $usage
     */
    private function reply(
        CoachMessage $message,
        CoachConversation $conversation,
        array $proposalIds,
        array $uiActions,
        array $usage,
        int $iterations,
        bool $truncated = false,
        bool $refused = false,
    ): CoachReply {
        $proposals = $proposalIds === []
            ? CoachProposal::query()->whereRaw('1 = 0')->get()
            : CoachProposal::query()
                ->where('conversation_id', $conversation->id)
                ->whereIn('id', $proposalIds)
                ->where('status', ProposalStatus::Pending->value)
                ->orderBy('id')
                ->get();

        return new CoachReply(
            text: $message->displayText(),
            proposals: $proposals,
            uiActions: $uiActions,
            usage: $usage,
            messageId: $message->id,
            iterations: $iterations,
            truncated: $truncated,
            refused: $refused,
        );
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
