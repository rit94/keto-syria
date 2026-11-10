<?php

namespace App\Services\Ai\Proposals;

use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\CoachConversation;
use App\Models\CoachMessage;
use App\Models\CoachProposal;
use App\Models\FoodLogEntry;
use App\Models\User;
use App\Services\Ai\Support\AiClock;
use App\Services\Logging\FoodLogService;
use App\Services\Plans\MealPlanService;
use App\Services\Recipes\RecipeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The only path from a coach suggestion to the database.
 *
 *   propose()  validated + engine-computed preview, stored as a pending CoachProposal. Writes
 *              nothing else.
 *   confirm()  the user approved (optionally after editing): re-validate, execute through
 *              FoodLogService / RecipeService / MealPlanService in one transaction, record the
 *              outcome and tell the conversation what really happened.
 *   reject()   the user declined.
 *
 * After every outcome a factual, app-generated note is appended to the conversation so the
 * model's next turn knows whether anything was saved. The model is never the source of that.
 */
class ProposalService
{
    public function __construct(
        private readonly ProposalPreviewBuilder $builder,
        private readonly FoodLogService $log,
        private readonly RecipeService $recipes,
        private readonly MealPlanService $plans,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ProposalInvalidException the payload cannot be calculated (nothing is stored)
     * @throws AuthorizationException the conversation is not the user's
     */
    public function propose(User $user, ?CoachConversation $conversation, ProposalType $type, array $payload): CoachProposal
    {
        if ($conversation !== null && (int) $conversation->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('coach.errors.forbidden'));
        }

        $built = $this->builder->build($user, $type, $payload);

        return CoachProposal::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation?->id,
            'type' => $type,
            'payload' => $built['payload'],
            'preview' => $built['preview'],
            'status' => ProposalStatus::Pending,
            'expires_at' => now()->addMinutes(max(1, (int) config('ai.proposal_ttl_minutes', 30))),
        ]);
    }

    /**
     * Recompute the preview for the proposal with the user's edits applied, without saving
     * anything – for a live "edit before confirming" form.
     *
     * @param  array<string, mixed>|null  $edits
     * @return array<string, mixed> the preview
     *
     * @throws ProposalInvalidException|AuthorizationException
     */
    public function preview(User $user, CoachProposal $proposal, ?array $edits = null): array
    {
        $this->authorize($user, $proposal);

        return $this->builder->build($user, $proposal->type, $this->applyEdits($proposal, $edits))['preview'];
    }

    /**
     * Execute a pending proposal.
     *
     * $edits may replace any of the type's editable top-level payload keys
     * (ProposalType::editableKeys()), e.g. for a meal:
     *   ['meal' => 'dinner', 'date' => '2026-11-10', 'items' => [['food_id' => 3, 'quantity' => 150, 'unit' => 'g']]]
     * Lists (items / ingredients) are replaced as a whole, so the user can also remove rows.
     *
     * Returns the proposal with status Confirmed (result holds the written ids) or Failed
     * (result['error'] holds the reason – nothing was written). Check `$proposal->status`.
     *
     * @param  array<string, mixed>|null  $edits
     *
     * @throws AuthorizationException not the user's proposal
     * @throws ProposalStateException already confirmed / rejected / failed, or expired
     * @throws ProposalInvalidException the user's edits are invalid – the proposal stays pending
     */
    public function confirm(User $user, CoachProposal $proposal, ?array $edits = null): CoachProposal
    {
        $this->authorize($user, $proposal);
        $this->claim($proposal);

        if ($proposal->expires_at !== null && $proposal->expires_at->isPast()) {
            $this->resolve($proposal, ProposalStatus::Expired, null);
            $this->note($proposal, 'expired without being confirmed. Nothing was saved or changed.');

            throw new ProposalStateException(ProposalStateException::EXPIRED);
        }

        try {
            $built = $this->builder->build($user, $proposal->type, $this->applyEdits($proposal, $edits));
        } catch (ProposalInvalidException $e) {
            if ($edits !== null && $edits !== []) {
                // the user's own edits are wrong: let them fix the form and try again
                $proposal->forceFill(['status' => ProposalStatus::Pending])->save();

                throw $e;
            }

            return $this->fail($proposal, $e->getMessage());
        }

        $proposal->forceFill(['payload' => $built['payload'], 'preview' => $built['preview']]);

        try {
            $result = DB::transaction(fn () => $this->execute($user, $proposal->type, $built['payload']));
        } catch (Throwable $e) {
            Log::warning('ai.proposal_failed', ['proposal_id' => $proposal->id, 'exception' => $e::class]);

            return $this->fail($proposal, $this->publicMessage($e));
        }

        $this->resolve($proposal, ProposalStatus::Confirmed, $result);
        $this->note($proposal, 'was CONFIRMED by the user and the database write succeeded. '.$this->describe($proposal->type, $result, $edits));

        return $proposal;
    }

    /**
     * @throws AuthorizationException|ProposalStateException
     */
    public function reject(User $user, CoachProposal $proposal): CoachProposal
    {
        $this->authorize($user, $proposal);
        $this->claim($proposal);

        $this->resolve($proposal, ProposalStatus::Rejected, null);
        $this->note($proposal, 'was REJECTED by the user. Nothing was saved or changed.');

        return $proposal;
    }

    /** Mark the user's overdue pending proposals as expired (housekeeping; safe to call any time). */
    public function expireStale(User $user): int
    {
        return CoachProposal::query()
            ->where('user_id', $user->id)
            ->where('status', ProposalStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => ProposalStatus::Expired->value, 'updated_at' => now()]);
    }

    // ───────────────────────────── internals ─────────────────────────────

    private function authorize(User $user, CoachProposal $proposal): void
    {
        if ((int) $proposal->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('coach.errors.forbidden'));
        }
    }

    /** Atomically take the proposal out of "pending" so two requests can never both execute it. */
    private function claim(CoachProposal $proposal): void
    {
        $claimed = CoachProposal::query()
            ->whereKey($proposal->id)
            ->where('status', ProposalStatus::Pending->value)
            ->update(['status' => ProposalStatus::Processing->value, 'updated_at' => now()]);

        if ($claimed !== 1) {
            $proposal->refresh();

            throw new ProposalStateException(
                $proposal->status === ProposalStatus::Expired ? ProposalStateException::EXPIRED : ProposalStateException::ALREADY_RESOLVED
            );
        }

        $proposal->refresh();
    }

    /**
     * @param  array<string, mixed>|null  $edits
     * @return array<string, mixed>
     */
    private function applyEdits(CoachProposal $proposal, ?array $edits): array
    {
        $payload = $proposal->payload ?? [];

        foreach ($proposal->type->editableKeys() as $key) {
            if ($edits !== null && array_key_exists($key, $edits)) {
                $payload[$key] = $edits[$key];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload  normalised by ProposalPreviewBuilder
     * @return array<string, mixed> ids of what was written
     */
    private function execute(User $user, ProposalType $type, array $payload): array
    {
        switch ($type) {
            case ProposalType::LogMeal:
                $entries = $this->log->logMany(
                    $user, $payload['items'], Meal::from($payload['meal']), AiClock::parseDate($payload['date']), LogSource::Coach,
                );

                return ['entry_ids' => $entries->pluck('id')->all()];

            case ProposalType::UpdateLogEntry:
                $entry = $this->ownEntry($user, (int) $payload['entry_id']);
                $this->log->update($entry, $payload['changes']);

                return ['entry_ids' => [$entry->id]];

            case ProposalType::DeleteLogEntry:
                $entry = $this->ownEntry($user, (int) $payload['entry_id']);
                $this->log->delete($entry);

                return ['deleted_entry_ids' => [$entry->id]];

            case ProposalType::SaveRecipe:
                return ['recipe_id' => $this->recipes->save($user, $payload + ['origin' => 'ai'])->id];

            case ProposalType::MealPlan:
                return ['meal_plan_id' => $this->plans->save($user, $payload + ['origin' => 'ai'])->id];
        }
    }

    private function ownEntry(User $user, int $id): FoodLogEntry
    {
        return FoodLogEntry::query()->where('user_id', $user->id)->findOrFail($id);
    }

    private function fail(CoachProposal $proposal, string $message): CoachProposal
    {
        $this->resolve($proposal, ProposalStatus::Failed, ['error' => $message]);
        $this->note($proposal, 'was confirmed by the user but the save FAILED, so NOTHING was saved or changed. Reason: '.$message);

        return $proposal;
    }

    /** @param array<string, mixed>|null $result */
    private function resolve(CoachProposal $proposal, ProposalStatus $status, ?array $result): void
    {
        $proposal->forceFill(['status' => $status, 'result' => $result])->save();
    }

    /** Append the true outcome to the conversation (role "note": app-generated, replayed to the model). */
    private function note(CoachProposal $proposal, string $outcome): void
    {
        if ($proposal->conversation_id === null) {
            return;
        }

        $text = sprintf('Proposal #%d (%s) %s', $proposal->id, $proposal->type->value, $outcome);

        CoachMessage::create([
            'conversation_id' => $proposal->conversation_id,
            'role' => CoachMessage::ROLE_NOTE,
            'content' => [['type' => 'text', 'text' => $text]],
            'meta' => ['kind' => 'proposal_note', 'proposal_id' => $proposal->id, 'status' => $proposal->status->value],
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>|null  $edits
     */
    private function describe(ProposalType $type, array $result, ?array $edits): string
    {
        $ids = fn (string $key) => implode(', ', array_map('strval', (array) ($result[$key] ?? [])));

        $what = match ($type) {
            ProposalType::LogMeal => 'Food log entries ['.$ids('entry_ids').'] were logged.',
            ProposalType::UpdateLogEntry => 'Food log entry ['.$ids('entry_ids').'] was updated.',
            ProposalType::DeleteLogEntry => 'Food log entry ['.$ids('deleted_entry_ids').'] was deleted.',
            ProposalType::SaveRecipe => 'Recipe ['.$ids('recipe_id').'] was saved.',
            ProposalType::MealPlan => 'Meal plan ['.$ids('meal_plan_id').'] was saved.',
        };

        if ($edits !== null && $edits !== []) {
            $what .= ' The user edited it before confirming ('.implode(', ', array_keys($edits))
                .'), so the saved values may differ from what you proposed; use the read tools for the current figures.';
        }

        return $what;
    }

    /** A message that is safe to store and show: domain errors as they are, anything else generic. */
    private function publicMessage(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->flatten()->first() ?? __('coach.proposals.errors.save_failed');
        }
        if ($e instanceof AuthorizationException || $e instanceof \DomainException || $e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }

        return __('coach.proposals.errors.save_failed');
    }
}
