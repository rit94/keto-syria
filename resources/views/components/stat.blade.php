{{--
    Single figure with a label (current weight, kcal eaten, streak…).

    Props
      label   string        (required)
      value   string|int|float|null   number (formatted with Western digits) or pre-formatted string; null → "—"
      decimals int          decimals when value is numeric (default 0)
      unit    string|null   small unit after the value
      hint    string|null   secondary line (e.g. "of 1,800")
      delta   float|null    change vs. previous; shown with a sign
      deltaUnit string|null
      deltaGood up|down|null  which direction counts as good (colours the delta); null = neutral
      icon    string|null
      lowerBound bool       prefix the value with "≥" (incomplete data)

    Usage
      <x-stat :label="__('progress.weight')" :value="$kg" :decimals="1" :unit="__('common.units.kg')" :delta="-0.6" deltaGood="down" />
--}}
@props([
    'label',
    'value' => null,
    'decimals' => 0,
    'unit' => null,
    'hint' => null,
    'delta' => null,
    'deltaUnit' => null,
    'deltaGood' => null,
    'icon' => null,
    'lowerBound' => false,
])

@php
    $display = is_numeric($value) ? \App\View\Format::number($value, (int) $decimals) : ($value ?? '—');

    $deltaTone = 'text-ink-muted';
    if ($delta !== null && $deltaGood && abs((float) $delta) > 0) {
        $isGood = ($deltaGood === 'down') === ((float) $delta < 0);
        $deltaTone = $isGood ? 'text-success-ink' : 'text-danger-ink';
    }
@endphp

<div {{ $attributes->class('min-w-0') }}>
    <p class="flex items-center gap-1.5 text-sm text-ink-muted">
        @if ($icon)<x-icon :name="$icon" size="sm" />@endif
        <span class="truncate">{{ $label }}</span>
    </p>

    <p class="mt-1 flex items-baseline gap-1.5">
        <span class="num text-2xl font-semibold leading-none text-ink" @if ($lowerBound) title="{{ __('common.at_least_hint') }}" @endif>{{ $lowerBound ? '≥ ' : '' }}{{ $display }}</span>
        @if ($unit)
            <span class="text-sm text-ink-muted">{{ $unit }}</span>
        @endif
    </p>

    @if ($delta !== null || $hint)
        <p class="mt-1.5 flex flex-wrap items-center gap-x-2 text-sm">
            @if ($delta !== null)
                <span class="font-medium {{ $deltaTone }}">
                    <span class="num">{{ (float) $delta > 0 ? '+' : ((float) $delta < 0 ? '−' : '') }}{{ \App\View\Format::number(abs((float) $delta), 1) }}</span>
                    @if ($deltaUnit) {{ $deltaUnit }} @endif
                </span>
            @endif
            @if ($hint)
                <span class="text-ink-muted">{{ $hint }}</span>
            @endif
        </p>
    @endif
</div>
