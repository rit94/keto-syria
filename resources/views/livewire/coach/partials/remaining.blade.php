{{--
    "Left for the day" after a proposal, from the engine's RemainingMacros array.
    $r  array|null  calories, net_carbs_g, protein_g, fat_g, is_lower_bound  (negative = over target)
--}}
@if (is_array($r))
    @php
        $items = [
            ['key' => 'calories', 'value' => $r['calories'] ?? 0, 'unit' => __('common.units.kcal')],
            ['key' => 'net_carbs', 'value' => $r['net_carbs_g'] ?? 0, 'unit' => __('common.units.g')],
            ['key' => 'protein', 'value' => $r['protein_g'] ?? 0, 'unit' => __('common.units.g')],
            ['key' => 'fat', 'value' => $r['fat_g'] ?? 0, 'unit' => __('common.units.g')],
        ];
    @endphp
    <div class="rounded-control bg-surface-2 p-3">
        <p class="text-sm font-medium text-ink">{{ __('coach_ui.proposal.remaining_after') }}</p>
        <ul class="mt-1.5 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            @foreach ($items as $item)
                @php($over = $item['value'] < 0)
                <li class="flex items-baseline justify-between gap-2">
                    <span class="text-ink-muted">{{ __('common.macros.'.$item['key']) }}</span>
                    <span @class(['font-medium', 'text-danger-ink' => $over, 'text-ink' => ! $over])>
                        {{ ! empty($r['is_lower_bound']) && ! $over ? '≤' : '' }}
                        <span class="num">{{ \App\View\Format::number(abs($item['value']), $item['key'] === 'calories' ? 0 : 1) }}</span>
                        {{ $item['unit'] }}
                        <span class="text-xs font-normal">{{ $over ? __('coach_ui.proposal.over') : __('coach_ui.proposal.left') }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
        @if (! empty($r['is_lower_bound']))
            <p class="mt-1.5 text-xs text-ink-muted">{{ __('common.at_least_hint') }}</p>
        @endif
    </div>
@endif
