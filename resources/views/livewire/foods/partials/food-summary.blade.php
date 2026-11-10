{{--
    Body of a food result row: name, state / preparation, quality badges and the per-100 g line.
    Display only – values are Food::per100g() rounded for reading.

    @param  App\Models\Food  $food
    @param  bool  $own  mark the user's own ingredients (default true)
--}}
@php
    $per100 = $food->per100g()->rounded();
    $descriptors = array_filter([
        $food->category?->label(),
        $food->state && $food->state !== \App\Enums\FoodState::NotApplicable ? $food->state->label() : null,
        $food->preparation?->label(),
    ]);
@endphp

<div class="min-w-0 flex-1">
    <p class="text-[0.9375rem] font-medium leading-snug text-ink">{{ $food->displayName() }}</p>
    <p class="mt-0.5 text-sm leading-snug text-ink-muted">{{ implode(' · ', $descriptors) }}</p>

    <dl class="mt-2 grid grid-cols-4 gap-2 text-center" aria-label="{{ __('foods.per_100g') }}">
        @foreach ([
            ['foods.short.kcal', $per100['calories'], 0, 'text-macro-kcal'],
            ['foods.short.net_carbs', $per100['net_carbs_g'], 1, 'text-macro-carbs'],
            ['foods.short.protein', $per100['protein_g'], 1, 'text-macro-protein'],
            ['foods.short.fat', $per100['fat_g'], 1, 'text-macro-fat'],
        ] as [$label, $value, $decimals, $colour])
            <div class="min-w-0 rounded-lg bg-surface-2 px-1 py-1.5">
                <dd class="num text-sm font-semibold leading-tight text-ink">@num($value, $decimals)</dd>
                <dt class="truncate text-xs leading-tight text-ink-muted">{{ __($label) }}</dt>
            </div>
        @endforeach
    </dl>

    @if ($food->is_estimate || $food->confidence || (($own ?? true) && $food->isCustom()))
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            @if (($own ?? true) && $food->isCustom())
                <x-badge variant="accent">{{ __('foods.mine_badge') }}</x-badge>
            @endif
            @if ($food->is_estimate)
                <x-badge estimate />
            @endif
            @if ($food->confidence)
                <x-badge :confidence="$food->confidence" />
            @endif
        </div>
    @endif
</div>
