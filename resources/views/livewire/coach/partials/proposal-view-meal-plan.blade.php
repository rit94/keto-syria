@php
    $targets = $preview['targets'] ?? null;
@endphp

<p class="text-sm text-ink-muted">
    <span class="num">{{ \App\View\Format::number($preview['days'] ?? null, 0) }}</span> {{ __('coach_ui.proposal.days') }}
    · {{ \App\View\Format::date($preview['starts_on'] ?? null, 'medium') }}
    · <span class="num">{{ \App\View\Format::number($preview['item_count'] ?? null, 0) }}</span> {{ __('coach_ui.proposal.items') }}
</p>

<div class="space-y-2">
    @foreach ($preview['by_day'] ?? [] as $day)
        <details class="group rounded-control border border-line" @if ($loop->first) open @endif>
            <summary class="flex min-h-touch cursor-pointer list-none items-center justify-between gap-3 px-3 py-2">
                <span class="text-[0.9375rem] font-medium text-ink">{{ \App\View\Format::date($day['date'], 'weekday') }}</span>
                <span class="flex items-center gap-2 text-sm text-ink-muted">
                    <span><span class="num">{{ \App\View\Format::number($day['totals']['calories'] ?? null, 0) }}</span> {{ __('common.units.kcal') }}</span>
                    <span class="hidden sm:inline">· <span class="num">{{ \App\View\Format::number($day['totals']['net_carbs_g'] ?? null, 1) }}</span> {{ __('common.units.g') }} {{ __('common.macros.net_carbs') }}</span>
                    <x-icon name="chevron-down" size="sm" class="transition-transform duration-150 group-open:rotate-180" />
                </span>
            </summary>
            <div class="space-y-2 border-t border-line px-3 py-2">
                <ul class="space-y-1">
                    @foreach ($day['items'] as $row)
                        <li class="flex items-start justify-between gap-3 text-sm">
                            <span class="min-w-0 text-ink">
                                <span class="text-ink-muted">{{ \App\Enums\Meal::tryFrom($row['meal'] ?? '')?->label() }}:</span>
                                {{ $itemName($row) }}
                            </span>
                            <span class="shrink-0 text-ink-muted">
                                <span class="num">{{ \App\View\Format::number($row['quantity'], 2) }}</span>
                                {{ $editor->unitLabel($row['food_id'] ?? null, $row['recipe_id'] ?? null, $row['unit'] ?? null) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                @include('livewire.coach.partials.nutrition', ['n' => $day['totals'] ?? [], 'small' => true])
                @if (! empty($day['remaining_vs_targets']))
                    @include('livewire.coach.partials.remaining', ['r' => $day['remaining_vs_targets']])
                @endif
            </div>
        </details>
    @endforeach
</div>
