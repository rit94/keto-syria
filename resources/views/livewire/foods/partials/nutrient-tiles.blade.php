{{--
    Four fixed-height tiles for a calculated amount (kcal, net carbs, protein, fat) plus the
    carbohydrate breakdown. Shows "—" while there is nothing to show, so the layout never jumps.

    @param  ?App\Domain\Nutrition\Nutrients  $nutrients  engine output (FoodLogService::preview) or null
--}}
@php
    $shown = $nutrients?->rounded();
    $g = __('common.units.g');
@endphp

<div aria-live="polite">
    <dl class="grid grid-cols-4 gap-2 text-center">
        @foreach ([
            ['common.macros.calories', $shown['calories'] ?? null, 0, __('common.units.kcal')],
            ['foods.short.net_carbs', $shown['net_carbs_g'] ?? null, 1, $g],
            ['common.macros.protein', $shown['protein_g'] ?? null, 1, $g],
            ['common.macros.fat', $shown['fat_g'] ?? null, 1, $g],
        ] as [$label, $value, $decimals, $unit])
            <div class="min-w-0 rounded-control bg-surface-2 px-1 py-2">
                <dd class="leading-tight text-ink">
                    <span class="num text-lg font-semibold">@num($value, $decimals)</span>
                    <span class="text-xs text-ink-muted">{{ $unit }}</span>
                </dd>
                <dt class="mt-0.5 text-xs leading-tight text-ink-muted">{{ __($label) }}</dt>
            </div>
        @endforeach
    </dl>

    <p class="mt-2 min-h-5 text-xs leading-5 text-ink-muted">
        @if ($shown)
            {{ __('foods.facts.carbs') }} <span class="num">@num($shown['carbs_g'], 1)</span> {{ $g }}
            ·
            {{ __('common.macros.fiber') }}
            @if ($shown['fiber_g'] === null)
                <span>{{ __('foods.unknown_short') }}</span>
            @else
                <span class="num">@num($shown['fiber_g'], 1)</span> {{ $g }}
            @endif
        @endif
    </p>
</div>
