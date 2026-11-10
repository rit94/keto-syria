<?php

namespace App\Livewire\Coach;

use App\Livewire\Coach\Support\ProposalEditor;
use App\Livewire\Coach\Support\Thread;
use App\Livewire\Concerns\Toasts;
use App\Models\CoachConversation;
use App\Models\CoachProposal;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\Coach\CoachService;
use App\Services\Ai\Coach\ImageInput;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Exceptions\AiNotConfiguredException;
use App\Services\Ai\Exceptions\AiRateLimitException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Proposals\ProposalInvalidException;
use App\Services\Ai\Proposals\ProposalService;
use App\Services\Ai\Proposals\ProposalStateException;
use App\Services\Ai\Proposals\ProposalStatus;
use App\View\Toast;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * The AI coach chat: conversation list, message thread, composer (text, photo, voice) and the
 * confirmation cards for what the coach proposes.
 *
 * Nothing is written to the diary here. Writes only happen through ProposalService::confirm(),
 * and a card says "saved" only when that call returned a proposal whose status is Confirmed.
 */
class Chat extends Component
{
    use Toasts;
    use WithFileUploads;

    public const STARTERS = ['tonight', 'eggplant', 'plan', 'carbs'];

    #[Locked]
    public ?int $conversationId = null;

    public string $message = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    /** Bumped to get a fresh <input type=file> after the photo was removed. */
    public int $photoKey = 0;

    /** @var array{type?: string, message?: string, seconds?: int} */
    #[Locked]
    public array $notice = [];

    /** @var array<int, list<array<string, mixed>>> assistant message id => UI actions of that turn */
    #[Locked]
    public array $uiActions = [];

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> form state of the proposal being edited */
    public array $edit = [];

    /** @var array<int, string> proposal id => message shown on its card */
    #[Locked]
    public array $cardErrors = [];

    /** @var array<string, list<string>> field errors of the last failed confirm of the edited proposal */
    #[Locked]
    public array $fieldErrors = [];

    public function mount(?CoachConversation $conversation = null): void
    {
        if ($conversation !== null) {
            Gate::authorize('view', $conversation);
            $this->conversationId = $conversation->id;
        }
    }

    // ───────────────────────────── composer ─────────────────────────────

    public function updatedPhoto(): void
    {
        $this->resetErrorBag('photo');

        if ($this->photo === null) {
            return;
        }

        try {
            ImageInput::assertAcceptable($this->photo);
        } catch (ValidationException $e) {
            $message = $e->errors()['image'][0] ?? __('coach.errors.image_invalid');
            $this->discardPhoto();
            $this->addError('photo', $message);
        }
    }

    public function removePhoto(): void
    {
        $this->resetErrorBag('photo');
        $this->discardPhoto();
    }

    public function useStarter(string $key): void
    {
        if (! in_array($key, self::STARTERS, true)) {
            return;
        }

        $this->message = __('coach_ui.starters.'.$key.'.prompt');
        $this->send(app(CoachService::class));
    }

    /** One turn can take a while (no streaming): the request simply stays open and the thread is reloaded from the database afterwards. */
    public function send(CoachService $coach): void
    {
        $this->resetErrorBag();
        $this->notice = [];

        $user = Auth::user();
        $conversation = $this->conversation();
        $created = false;

        if ($conversation !== null) {
            Gate::authorize('update', $conversation);
        }

        try {
            if ($conversation === null) {
                $conversation = $coach->startConversation($user);
                $created = true;
            }

            $reply = $coach->send($user, $conversation, $this->message, $this->photo);
        } catch (ValidationException $e) {
            $this->cleanUp($conversation, $created);
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'image' ? 'photo' : ($field === 'text' ? 'message' : $field), $messages[0]);
            }

            return;
        } catch (AuthorizationException) {
            abort(403);
        } catch (AiNotConfiguredException $e) {
            $this->cleanUp($conversation, $created);
            $this->notice = ['type' => 'not_configured', 'message' => $e->getMessage()];

            return;
        } catch (AiRateLimitException $e) {
            $this->cleanUp($conversation, $created);
            $this->notice = ['type' => 'rate_limited', 'message' => $e->getMessage(), 'seconds' => $e->retryAfterSeconds];

            return;
        } catch (AiUnavailableException $e) {
            // the user's message was removed by CoachService, so what they typed stays in the composer for a retry
            $this->cleanUp($conversation, $created);
            $this->notice = ['type' => 'unavailable', 'message' => $e->getMessage()];

            return;
        } catch (AiException $e) {
            $this->cleanUp($conversation, $created);
            $this->notice = ['type' => 'error', 'message' => $e->getMessage()];

            return;
        }

        $this->conversationId = $conversation->id;
        $this->uiActions[$reply->messageId] = $this->safeActions($reply->uiActions);
        $this->message = '';
        $this->discardPhoto();

        if ($created) {
            $this->js('history.replaceState(history.state, "", '.Js::from(route('coach.show', $conversation)).')');
        }

        $this->dispatch('coach-scroll');
    }

    // ───────────────────────────── conversations ─────────────────────────────

    public function deleteConversation(int $id): void
    {
        $conversation = CoachConversation::query()->findOrFail($id);
        Gate::authorize('delete', $conversation);

        foreach ($conversation->messages()->get() as $message) {
            $image = $message->meta['image'] ?? null;
            if (is_array($image) && isset($image['disk'], $image['path'])) {
                try {
                    Storage::disk((string) $image['disk'])->delete((string) $image['path']);
                } catch (Throwable) {
                    // best effort
                }
            }
        }

        $conversation->delete();
        $this->dispatch('close-modal');

        if ($this->conversationId === $conversation->id) {
            Toast::flash(__('coach_ui.conversation_deleted'));
            $this->redirectRoute('coach', navigate: true);

            return;
        }

        $this->toast(__('coach_ui.conversation_deleted'));
    }

    // ───────────────────────────── proposals ─────────────────────────────

    public function startEdit(int $id, ProposalEditor $editor): void
    {
        $proposal = $this->proposal($id);

        if (! $proposal->isPending() || $proposal->type->editableKeys() === []) {
            return;
        }

        $this->editingId = $proposal->id;
        $this->edit = $editor->initial($proposal);
        $this->fieldErrors = [];
        unset($this->cardErrors[$id]);
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->edit = [];
        $this->fieldErrors = [];
    }

    public function removeRow(string $list, int $index): void
    {
        if (! in_array($list, ['items', 'ingredients'], true) || ! isset($this->edit[$list][$index])) {
            return;
        }

        unset($this->edit[$list][$index]);
        $this->edit[$list] = array_values($this->edit[$list]);
        $this->fieldErrors = [];
    }

    public function confirmProposal(int $id, ProposalService $proposals, ProposalEditor $editor): void
    {
        $proposal = $this->proposal($id);
        $user = Auth::user();
        $editing = $this->editingId === $proposal->id;

        unset($this->cardErrors[$id]);
        $this->fieldErrors = [];

        try {
            $proposal = $proposals->confirm($user, $proposal, $editing ? $editor->edits($proposal, $this->edit) : null);
        } catch (ProposalInvalidException $e) {
            // the edits are wrong: the proposal is still pending, the form shows what to fix
            $this->fieldErrors = $e->messagesByField();
            $this->cardErrors[$id] = $e->getMessage();

            return;
        } catch (ProposalStateException $e) {
            $this->cardErrors[$id] = $e->getMessage();
            $this->cancelEdit();

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->cancelEdit();

        if ($proposal->status === ProposalStatus::Confirmed) {
            $this->toast(__('coach_ui.proposal.saved_toast'));
        } else {
            // Failed: nothing was written and the card says why. Never reported as saved.
            $this->toast(__('coach_ui.proposal.failed_toast'), 'error');
        }

        $this->dispatch('coach-scroll');
    }

    public function rejectProposal(int $id, ProposalService $proposals): void
    {
        $proposal = $this->proposal($id);

        unset($this->cardErrors[$id]);

        try {
            $proposals->reject(Auth::user(), $proposal);
        } catch (ProposalStateException $e) {
            $this->cardErrors[$id] = $e->getMessage();
        } catch (AuthorizationException) {
            abort(403);
        }

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }

    // ───────────────────────────── render ─────────────────────────────

    public function render(AnthropicClient $client, ProposalService $proposals)
    {
        $user = Auth::user();
        $conversation = $this->conversation();
        $editor = new ProposalEditor;

        $editingPreview = null;
        $editErrors = [];

        if ($this->editingId !== null) {
            $editing = CoachProposal::query()->where('user_id', $user->id)->find($this->editingId);

            if ($editing === null || ! $editing->isPending()) {
                $this->editingId = null;
            } else {
                try {
                    $editingPreview = $proposals->preview($user, $editing, $editor->edits($editing, $this->edit));
                } catch (ProposalInvalidException $e) {
                    $editErrors = array_map(fn (array $messages) => $messages[0], $e->messagesByField());
                }
            }
        }

        foreach ($this->fieldErrors as $field => $messages) {
            $editErrors[$field] ??= $messages[0];
        }

        return view('livewire.coach.chat', [
            'conversation' => $conversation,
            'thread' => $conversation === null ? [] : Thread::build($conversation),
            'conversations' => CoachConversation::query()->where('user_id', $user->id)->orderByDesc('updated_at')->orderByDesc('id')->limit(40)->get(),
            'configured' => $client->isConfigured(),
            'editor' => $editor,
            'editingPreview' => $editingPreview,
            'editErrors' => $editErrors,
            'photoUrl' => $this->photoUrl(),
        ])->title(__('coach_ui.title'))->layoutData(['wide' => true]);
    }

    // ───────────────────────────── internals ─────────────────────────────

    private function conversation(): ?CoachConversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        $conversation = CoachConversation::query()->find($this->conversationId);
        abort_if($conversation === null, 404);
        Gate::authorize('view', $conversation);

        return $conversation;
    }

    /** A proposal of this conversation that belongs to the signed-in user. */
    private function proposal(int $id): CoachProposal
    {
        $proposal = CoachProposal::query()->findOrFail($id);
        Gate::authorize('update', $proposal);
        abort_unless($proposal->conversation_id !== null && $proposal->conversation_id === $this->conversationId, 404);

        return $proposal;
    }

    /** Remove a conversation that was created for a turn which never happened. */
    private function cleanUp(?CoachConversation $conversation, bool $created): void
    {
        if ($created && $conversation !== null && $conversation->messages()->doesntExist()) {
            $conversation->delete();
        }
    }

    private function discardPhoto(): void
    {
        try {
            $this->photo?->delete();
        } catch (Throwable) {
            // temporary files are purged by Livewire anyway
        }

        $this->photo = null;
        $this->photoKey++;
    }

    private function photoUrl(): ?string
    {
        try {
            return $this->photo?->isPreviewable() ? $this->photo->temporaryUrl() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Only actions the page knows how to show, with plain-string fields.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    private function safeActions(array $actions): array
    {
        $safe = [];

        foreach ($actions as $action) {
            if (($action['type'] ?? null) === 'open_custom_food_form' && is_string($action['name'] ?? null) && trim($action['name']) !== '') {
                $safe[] = ['type' => 'open_custom_food_form', 'name' => mb_substr(trim($action['name']), 0, 120)];
            }
        }

        return $safe;
    }
}
