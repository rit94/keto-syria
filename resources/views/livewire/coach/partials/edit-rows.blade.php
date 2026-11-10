{{--
    Editable rows (quantity, unit, optional "weighed cooked", remove) for a proposal's list.
    $list    'items' | 'ingredients'
    $cooked  bool  offer the raw/cooked checkbox (log_meal only)
    $prefix  string|null  optional per-row caption (meal plans: day + meal)
    Needs the parent's $proposal, $edit, $editErrors, $editor.
--}}
@php
    $rows = $edit[$list] ?? [];
@endphp

@foreach ($rows as $i => $row)
    @php
        $foodId = isset($row['food_id']) ? (int) $row['food_id'] : null;
        $recipeId = isset($row['recipe_id']) ? (int) $row['recipe_id'] : null;
        $path = $list.'.'.$i;
        $rowError = collect($editErrors)->filter(fn ($message, $field) => str_starts_with($field, $path.'.') && ! str_ends_with($field, '.quantity') && ! str_ends_with($field, '.unit') && ! str_ends_with($field, '.weighed_cooked'))->first()
            ?? ($editErrors[$path] ?? null);
    @endphp

    <div class="space-y-2 rounded-control border border-line p-3" wire:key="edit-{{ $proposal->id }}-{{ $list }}-{{ $i }}">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                @if (! empty($row['planned_on']))
                    <p class="text-xs text-ink-muted">{{ \App\View\Format::date($row['planned_on'], 'short') }} · {{ \App\Enums\Meal::tryFrom($row['meal'] ?? '')?->label() }}</p>
                @endif
                <p class="text-[0.9375rem] font-medium text-ink">{{ $editor->label($foodId, $recipeId) }}</p>
            </div>
            @if (count($rows) > 1)
                <button
                    type="button"
                    wire:click="removeRow('{{ $list }}', {{ $i }})"
                    class="-me-2 -mt-1 flex size-touch shrink-0 items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-danger-soft hover:text-danger-ink"
                    aria-label="{{ __('coach_ui.proposal.remove_row', ['name' => $editor->label($foodId, $recipeId)]) }}"
                >
                    <x-icon name="trash" size="sm" />
                </button>
            @endif
        </div>

        @if ($rowError)
            <p class="text-sm text-danger-ink" role="alert">{{ $rowError }}</p>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <x-number-stepper
                wire:model.live.debounce.400ms="edit.{{ $path }}.quantity"
                :label="__('coach_ui.proposal.quantity')"
                :step="($row['unit'] ?? 'g') === 'g' ? 10 : 0.5"
                :error="$editErrors[$path.'.quantity'] ?? null"
            />
            <x-select
                wire:model.live="edit.{{ $path }}.unit"
                :label="__('coach_ui.proposal.unit')"
                :options="$editor->unitOptions($foodId, $recipeId)"
                :selected="$row['unit'] ?? null"
                :error="$editErrors[$path.'.unit'] ?? null"
            />
        </div>

        @if (($cooked ?? false) && $editor->canWeighCooked($foodId))
            <x-checkbox wire:model.live="edit.{{ $path }}.weighed_cooked" :label="__('coach_ui.proposal.weighed_cooked')" />
            @if (! empty($editErrors[$path.'.weighed_cooked']))
                <p class="text-sm text-danger-ink" role="alert">{{ $editErrors[$path.'.weighed_cooked'] }}</p>
            @endif
        @endif
    </div>
@endforeach

@if (! empty($editErrors[$list]))
    <p class="text-sm text-danger-ink" role="alert">{{ $editErrors[$list] }}</p>
@endif
