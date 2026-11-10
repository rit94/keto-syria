@if (! empty($editErrors['items']))
    <p class="text-sm text-danger-ink" role="alert">{{ $editErrors['items'] }}</p>
@endif

<x-input wire:model.live.debounce.500ms="edit.name" :label="__('coach_ui.proposal.plan_name')" :error="$editErrors['name'] ?? null" />

<div class="grid grid-cols-2 gap-3">
    <x-input type="date" wire:model.live="edit.starts_on" :label="__('coach_ui.proposal.starts_on')" :error="$editErrors['starts_on'] ?? null" />
    <x-number-stepper wire:model.live.debounce.400ms="edit.days" :label="__('coach_ui.proposal.days_label')" :step="1" :min="1" :max="31" :error="$editErrors['days'] ?? null" />
</div>

@include('livewire.coach.partials.edit-rows', ['list' => 'items', 'cooked' => false])

@if ($editingPreview)
    <p class="text-sm text-ink-muted">
        <span class="num">{{ \App\View\Format::number($editingPreview['item_count'] ?? null, 0) }}</span> {{ __('coach_ui.proposal.items') }}
    </p>
@else
    <p class="text-sm text-ink-muted">{{ __('coach_ui.proposal.fix_to_preview') }}</p>
@endif
