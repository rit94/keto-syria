@php
    $mealOptions = collect(\App\Enums\Meal::cases())->mapWithKeys(fn ($meal) => [$meal->value => $meal->label()])->all();
@endphp

<div class="grid grid-cols-2 gap-3">
    <x-select wire:model.live="edit.meal" :label="__('coach_ui.proposal.meal')" :options="$mealOptions" :selected="$edit['meal'] ?? null" :error="$editErrors['meal'] ?? null" />
    <x-input type="date" wire:model.live="edit.date" :label="__('coach_ui.proposal.date')" :error="$editErrors['date'] ?? null" />
</div>

@include('livewire.coach.partials.edit-rows', ['list' => 'items', 'cooked' => true])

@if ($editingPreview)
    <div wire:loading.class="opacity-60" class="transition-opacity duration-150 space-y-3">
        @include('livewire.coach.partials.nutrition', ['n' => $editingPreview['totals'] ?? [], 'caption' => __('coach_ui.proposal.new_total')])
        @include('livewire.coach.partials.remaining', ['r' => $editingPreview['day']['remaining_after'] ?? null])
    </div>
@else
    <p class="text-sm text-ink-muted">{{ __('coach_ui.proposal.fix_to_preview') }}</p>
@endif
