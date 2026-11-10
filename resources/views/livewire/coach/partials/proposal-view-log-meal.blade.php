@php
    $dayInfo = $preview['day'] ?? [];
@endphp

<p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-muted">
    <span class="inline-flex items-center gap-1.5"><x-icon name="clock" size="sm" /> {{ \App\Enums\Meal::tryFrom($preview['meal'] ?? '')?->label() }}</span>
    <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" size="sm" /> {{ \App\View\Format::date($preview['date'] ?? null, 'weekday') }}</span>
</p>

<ul class="divide-y divide-line rounded-control border border-line">
    @foreach ($preview['items'] ?? [] as $row)
        <li class="flex items-start justify-between gap-3 px-3 py-2.5">
            <div class="min-w-0">
                <p class="text-[0.9375rem] font-medium text-ink">{{ $itemName($row) }}</p>
                <p class="text-sm text-ink-muted">
                    <span class="num">{{ \App\View\Format::number($row['quantity'], 2) }}</span>
                    {{ $editor->unitLabel($row['food_id'] ?? null, null, $row['unit'] ?? null) }}
                    @if (! empty($row['weighed_cooked'])) · {{ __('coach_ui.proposal.weighed_cooked') }} @endif
                </p>
            </div>
            <div class="shrink-0 text-end">
                <p class="text-sm font-semibold text-ink"><span class="num">{{ \App\View\Format::number($row['nutrition']['calories'] ?? null, 0) }}</span> {{ __('common.units.kcal') }}</p>
                <p class="text-xs text-ink-muted"><span class="num">{{ \App\View\Format::number($row['nutrition']['net_carbs_g'] ?? null, 1) }}</span> {{ __('common.units.g') }} {{ __('common.macros.net_carbs') }}</p>
                @if ($row['is_estimate'] ?? false)<x-badge estimate class="mt-1" />@endif
            </div>
        </li>
    @endforeach
</ul>

@include('livewire.coach.partials.nutrition', ['n' => $preview['totals'] ?? [], 'caption' => __('coach_ui.proposal.total')])
@include('livewire.coach.partials.remaining', ['r' => $dayInfo['remaining_after'] ?? null])
