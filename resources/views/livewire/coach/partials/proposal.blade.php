{{--
    Confirmation card for one CoachProposal. Needs: $proposal, $editor, $editingId, $editingPreview, $editErrors, $edit, $cardErrors.

    Nothing here is saved until Confirm succeeds; the "Saved" state is read from the proposal's own status
    (App\Services\Ai\Proposals\ProposalStatus::Confirmed), never assumed.
--}}
@php
    $type = $proposal->type->value;
    $slug = str_replace('_', '-', $type);
    $expired = $proposal->isExpired();
    $statusValue = $expired ? 'expired' : $proposal->status->value;
    $isPending = $statusValue === 'pending';
    $editing = $isPending && $editingId === $proposal->id;
    $canEdit = $proposal->type->editableKeys() !== [];
    $preview = $editing && $editingPreview ? $editingPreview : ($proposal->preview ?? []);
    $showBody = in_array($statusValue, ['pending', 'processing', 'confirmed', 'failed'], true);
    $icons = ['log_meal' => 'flame', 'update_log_entry' => 'edit', 'delete_log_entry' => 'trash', 'save_recipe' => 'book', 'meal_plan' => 'calendar'];
    $badge = ['pending' => 'warning', 'processing' => 'info', 'confirmed' => 'success', 'rejected' => 'neutral', 'failed' => 'danger', 'expired' => 'neutral'][$statusValue];
    $locale = app()->getLocale();
    $itemName = fn (array $row) => $locale === 'ar' ? ($row['name_ar'] ?? $row['name'] ?? '') : (($row['name_en'] ?? null) ?: ($row['name_ar'] ?? $row['name'] ?? ''));
@endphp

<x-card as="article" padding="sm" wire:key="proposal-{{ $proposal->id }}" class="w-full sm:max-w-[92%]" aria-labelledby="proposal-{{ $proposal->id }}-title">
    <div class="flex items-start gap-3">
        <span @class([
            'flex size-9 shrink-0 items-center justify-center rounded-full',
            'bg-danger-soft text-danger-ink' => $type === 'delete_log_entry',
            'bg-accent-soft text-accent-ink' => $type !== 'delete_log_entry',
        ])><x-icon :name="$icons[$type]" size="sm" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-medium text-ink-muted">{{ __('coach_ui.proposal.type.'.$type) }}</p>
            <h3 id="proposal-{{ $proposal->id }}-title" class="text-[0.9375rem] font-semibold leading-snug text-ink">
                {{ $editing ? __('coach_ui.proposal.editing') : $proposal->summary() }}
            </h3>
        </div>
        <x-badge :variant="$badge" class="shrink-0">{{ $expired ? __('coach.proposals.status.expired') : $proposal->status->label() }}</x-badge>
    </div>

    @if ($showBody)
        <div class="mt-3 space-y-3">
            @if ($editing)
                @include('livewire.coach.partials.proposal-edit-'.$slug)
            @else
                @include('livewire.coach.partials.proposal-view-'.$slug)
            @endif

            @if ($preview['is_estimate'] ?? false)
                <p class="flex items-center gap-2 text-sm text-ink-muted">
                    <x-badge estimate />
                    <span>{{ __('coach_ui.proposal.estimate_note') }}</span>
                </p>
            @endif
        </div>
    @endif

    {{-- Messages about this card --}}
    @if (! empty($cardErrors[$proposal->id]))
        <x-alert variant="danger" class="mt-3" wire:key="proposal-{{ $proposal->id }}-error">{{ $cardErrors[$proposal->id] }}</x-alert>
    @endif

    {{-- Footer --}}
    <div class="mt-3">
        @if ($isPending)
            <p class="mb-2 text-sm text-ink-muted">{{ __('coach.proposals.not_saved_yet') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <x-button wire:click="confirmProposal({{ $proposal->id }})" icon="check" :variant="$type === 'delete_log_entry' ? 'danger' : 'primary'">
                    {{ $type === 'delete_log_entry' ? __('coach_ui.proposal.confirm_delete') : __('coach.proposals.actions.confirm') }}
                </x-button>
                @if ($editing)
                    <x-button wire:click="cancelEdit" variant="secondary">{{ __('coach_ui.proposal.cancel_edit') }}</x-button>
                @elseif ($canEdit)
                    <x-button wire:click="startEdit({{ $proposal->id }})" variant="secondary" icon="edit">{{ __('coach.proposals.actions.edit') }}</x-button>
                @endif
                <x-button wire:click="rejectProposal({{ $proposal->id }})" variant="ghost">{{ __('coach.proposals.actions.reject') }}</x-button>
            </div>
        @elseif ($statusValue === 'confirmed')
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <p class="flex items-center gap-1.5 text-sm font-medium text-success-ink"><x-icon name="check" size="sm" /> {{ __('coach_ui.proposal.saved') }}</p>
                @php
                    $result = $proposal->result ?? [];
                    $target = match ($type) {
                        'save_recipe' => isset($result['recipe_id']) && \Illuminate\Support\Facades\Route::has('recipes.show') ? ['url' => route('recipes.show', $result['recipe_id']), 'label' => __('coach_ui.proposal.open_recipe')] : null,
                        'meal_plan' => isset($result['meal_plan_id']) && \Illuminate\Support\Facades\Route::has('plans.show') ? ['url' => route('plans.show', $result['meal_plan_id']), 'label' => __('coach_ui.proposal.open_plan')] : null,
                        default => \Illuminate\Support\Facades\Route::has('today') ? ['url' => route('today'), 'label' => __('coach_ui.proposal.open_today')] : null,
                    };
                @endphp
                @if ($target)
                    <x-button size="sm" variant="secondary" :href="$target['url']" navigate iconEnd="chevron-end">{{ $target['label'] }}</x-button>
                @endif
            </div>
        @elseif ($statusValue === 'failed')
            <x-alert variant="danger" :title="__('coach_ui.proposal.failed')">
                {{ $proposal->result['error'] ?? __('coach.proposals.errors.save_failed') }}
                <span class="block text-sm text-ink-muted">{{ __('coach_ui.proposal.nothing_saved') }}</span>
            </x-alert>
        @elseif ($statusValue === 'rejected')
            <p class="text-sm text-ink-muted">{{ __('coach_ui.proposal.rejected_note') }}</p>
        @elseif ($statusValue === 'expired')
            <p class="text-sm text-ink-muted">{{ __('coach.proposals.errors.expired') }}</p>
        @elseif ($statusValue === 'processing')
            <p class="text-sm text-ink-muted">{{ __('coach.proposals.status.processing') }}</p>
        @endif
    </div>
</x-card>
