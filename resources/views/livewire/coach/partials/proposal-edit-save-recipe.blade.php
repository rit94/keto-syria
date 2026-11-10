<x-input wire:model.live.debounce.500ms="edit.name" :label="__('coach_ui.proposal.recipe_name')" :error="$editErrors['name'] ?? null" />

<div class="grid grid-cols-2 gap-3">
    <x-number-stepper wire:model.live.debounce.400ms="edit.servings" :label="__('coach_ui.proposal.servings_label')" :step="1" :min="0.5" :error="$editErrors['servings'] ?? null" />
    <x-number-stepper wire:model.live.debounce.400ms="edit.cooked_weight_g" :label="__('coach_ui.proposal.cooked_weight')" :step="50" :unit="__('common.units.g')" :error="$editErrors['cooked_weight_g'] ?? null" />
</div>

<x-textarea wire:model="edit.description" :label="__('coach_ui.proposal.description')" rows="2" optional />
<x-textarea wire:model="edit.instructions" :label="__('coach_ui.proposal.instructions')" rows="4" optional />

@include('livewire.coach.partials.edit-rows', ['list' => 'ingredients', 'cooked' => false])

@if ($editingPreview)
    @include('livewire.coach.partials.nutrition', ['n' => $editingPreview['per_serving'] ?? [], 'caption' => __('coach_ui.proposal.per_serving')])
@else
    <p class="text-sm text-ink-muted">{{ __('coach_ui.proposal.fix_to_preview') }}</p>
@endif
