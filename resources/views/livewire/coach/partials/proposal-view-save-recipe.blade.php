@php
    $servings = $preview['servings'] ?? null;
@endphp

<p class="text-sm text-ink-muted">
    <span class="num">{{ \App\View\Format::number($servings, 2) }}</span> {{ __('coach_ui.proposal.servings') }}
    @if (! empty($preview['finished_weight_g']))
        · {{ __('coach_ui.proposal.finished_weight') }}:
        <span class="num">{{ \App\View\Format::number($preview['finished_weight_g'], 0) }}</span> {{ __('common.units.g') }}
        @unless ($preview['finished_weight_is_measured'] ?? false) ({{ __('coach_ui.proposal.weight_estimated') }}) @endunless
    @endif
</p>

<ul class="divide-y divide-line rounded-control border border-line">
    @foreach ($preview['ingredients'] ?? [] as $row)
        <li class="flex items-start justify-between gap-3 px-3 py-2">
            <span class="min-w-0 text-[0.9375rem] text-ink">{{ $itemName($row) }}</span>
            <span class="shrink-0 text-sm text-ink-muted">
                <span class="num">{{ \App\View\Format::number($row['quantity'], 2) }}</span>
                {{ $editor->unitLabel($row['food_id'] ?? null, null, $row['unit'] ?? null) }}
            </span>
        </li>
    @endforeach
</ul>

@include('livewire.coach.partials.nutrition', ['n' => $preview['per_serving'] ?? [], 'caption' => __('coach_ui.proposal.per_serving')])
<p class="text-sm text-ink-muted">
    {{ __('coach_ui.proposal.whole_recipe') }}:
    <span class="num">{{ \App\View\Format::number($preview['total']['calories'] ?? null, 0) }}</span> {{ __('common.units.kcal') }}
    · <span class="num">{{ \App\View\Format::number($preview['total']['net_carbs_g'] ?? null, 1) }}</span> {{ __('common.units.g') }} {{ __('common.macros.net_carbs') }}
</p>
