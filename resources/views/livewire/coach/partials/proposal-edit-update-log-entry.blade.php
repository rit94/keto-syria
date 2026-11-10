@php
    $before = $proposal->preview['before'] ?? [];
    $mealOptions = collect(\App\Enums\Meal::cases())->mapWithKeys(fn ($meal) => [$meal->value => $meal->label()])->all();
@endphp

<p class="text-[0.9375rem] font-medium text-ink">{{ $before['description'] ?? '' }}</p>

@if (! empty($editErrors['changes']))
    <p class="text-sm text-danger-ink" role="alert">{{ $editErrors['changes'] }}</p>
@endif

<div class="grid grid-cols-2 gap-3">
    <x-number-stepper
        wire:model.live.debounce.400ms="edit.changes.quantity"
        :label="__('coach_ui.proposal.quantity')"
        :step="($edit['changes']['unit'] ?? 'g') === 'g' ? 10 : 0.5"
        :error="$editErrors['changes.quantity'] ?? null"
    />
    <x-select
        wire:model.live="edit.changes.unit"
        :label="__('coach_ui.proposal.unit')"
        :options="$editor->unitOptions($before['food_id'] ?? null, $before['recipe_id'] ?? null)"
        :selected="$edit['changes']['unit'] ?? null"
        :error="$editErrors['changes.unit'] ?? null"
    />
    <x-select wire:model.live="edit.changes.meal" :label="__('coach_ui.proposal.meal')" :options="$mealOptions" :selected="$edit['changes']['meal'] ?? null" :error="$editErrors['changes.meal'] ?? null" />
    <x-input type="date" wire:model.live="edit.changes.logged_on" :label="__('coach_ui.proposal.date')" :error="$editErrors['changes.logged_on'] ?? null" />
</div>

@if ($editingPreview)
    @include('livewire.coach.partials.nutrition', ['n' => $editingPreview['after']['nutrition'] ?? [], 'caption' => __('coach_ui.proposal.after_nutrition')])
@else
    <p class="text-sm text-ink-muted">{{ __('coach_ui.proposal.fix_to_preview') }}</p>
@endif
