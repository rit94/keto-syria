@php
    $before = $preview['before'] ?? [];
    $after = $preview['after'] ?? [];
    $unitLabel = fn (array $row) => $editor->unitLabel($row['food_id'] ?? null, $row['recipe_id'] ?? null, $row['unit'] ?? null);
@endphp

<p class="text-[0.9375rem] font-medium text-ink">{{ $before['description'] ?? '' }}</p>

<div class="grid gap-2 sm:grid-cols-2">
    <div class="rounded-control border border-line p-3">
        <p class="text-xs font-medium text-ink-muted">{{ __('coach_ui.proposal.before') }}</p>
        <p class="mt-1 text-sm text-ink">
            <span class="num">{{ \App\View\Format::number($before['quantity'] ?? null, 2) }}</span> {{ $unitLabel($before) }}
        </p>
        <p class="text-sm text-ink-muted">{{ \App\Enums\Meal::tryFrom($before['meal'] ?? '')?->label() }} · {{ \App\View\Format::date($before['date'] ?? null, 'short') }}</p>
        <p class="mt-1 text-sm text-ink-muted"><span class="num">{{ \App\View\Format::number($before['nutrition']['calories'] ?? null, 0) }}</span> {{ __('common.units.kcal') }}</p>
    </div>
    <div class="rounded-control border border-accent/40 bg-accent-soft/40 p-3">
        <p class="text-xs font-medium text-accent-ink">{{ __('coach_ui.proposal.after') }}</p>
        <p class="mt-1 text-sm font-medium text-ink">
            <span class="num">{{ \App\View\Format::number($after['quantity'] ?? null, 2) }}</span> {{ $unitLabel($before + ['unit' => $after['unit'] ?? null]) }}
        </p>
        <p class="text-sm text-ink-muted">{{ \App\Enums\Meal::tryFrom($after['meal'] ?? '')?->label() }} · {{ \App\View\Format::date($after['date'] ?? null, 'short') }}</p>
    </div>
</div>

@include('livewire.coach.partials.nutrition', ['n' => $after['nutrition'] ?? [], 'caption' => __('coach_ui.proposal.after_nutrition')])
