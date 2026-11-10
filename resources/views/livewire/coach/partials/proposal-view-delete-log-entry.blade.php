@php
    $entry = $preview['entry'] ?? [];
@endphp

<div class="rounded-control border border-line p-3">
    <p class="text-[0.9375rem] font-medium text-ink">{{ $entry['description'] ?? '' }}</p>
    <p class="text-sm text-ink-muted">
        <span class="num">{{ \App\View\Format::number($entry['quantity'] ?? null, 2) }}</span>
        {{ $editor->unitLabel($entry['food_id'] ?? null, $entry['recipe_id'] ?? null, $entry['unit'] ?? null) }}
        · {{ \App\Enums\Meal::tryFrom($entry['meal'] ?? '')?->label() }}
        · {{ \App\View\Format::date($entry['date'] ?? null, 'short') }}
    </p>
</div>

@include('livewire.coach.partials.nutrition', ['n' => $entry['nutrition'] ?? [], 'caption' => __('coach_ui.proposal.removed_nutrition')])
