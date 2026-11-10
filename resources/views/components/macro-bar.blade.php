{{--
    Linear progress for one macro: label, value / target, remaining.

    Values come from the nutrition engine (DailySummary). This component only formats and draws them.

    Props
      label       string        macro name (required)
      value       float         amount consumed
      target      float|null    daily target; null hides the bar fill and the remaining line
      unit        string|null   e.g. __('common.units.g')
      macro       kcal|carbs|protein|fat|fiber   colour series (default kcal)
      limit       bool          true (default): over the target is an over-target state (danger colour,
                                "over" caption, tick showing where the target sits). false: target is a goal.
      lowerBound  bool          value is a minimum (some foods lack this nutrient): shows "≥" before the
                                value and a hatched end on the bar
      decimals    int           default 0
      left        float|null    remaining amount from the engine (RemainingMacros; negative = over). Pass it
                                when you have it; otherwise target − value is displayed.
      remaining   bool          show the remaining / over line (default true)

    Usage
      <x-macro-bar :label="__('common.macros.protein')" macro="protein" :limit="false"
                   :value="$summary->totals->protein" :target="$summary->targets?->protein" :unit="__('common.units.g')" />
      <x-macro-bar :label="__('common.macros.fiber')" macro="fiber" :limit="false" :value="$fiber" :lowerBound="true" :unit="__('common.units.g')" />
--}}
@props([
    'label',
    'value' => 0,
    'target' => null,
    'unit' => null,
    'macro' => 'kcal',
    'limit' => true,
    'lowerBound' => false,
    'decimals' => 0,
    'left' => null,
    'remaining' => true,
])

@php
    $value = (float) $value;
    $target = $target === null ? null : (float) $target;
    $hasTarget = $target !== null && $target > 0;

    $ratio = $hasTarget ? $value / $target : 0;
    $over = $hasTarget && $limit && round($value, $decimals) > round($target, $decimals);
    $reached = $hasTarget && ! $limit && $ratio >= 1;
    // Prefer the engine's RemainingMacros figure; fall back to the plain difference of the two given numbers.
    $difference = $left !== null ? abs((float) $left) : ($hasTarget ? abs($target - $value) : 0);

    $width = round(min(max($ratio, 0), 1) * 100, 2);
    // When over, the bar is full and a tick marks where the target sits within the value.
    $targetTick = $over && $value > 0 ? round($target / $value * 100, 2) : null;

    $fills = [
        'kcal' => 'bg-macro-kcal',
        'carbs' => 'bg-macro-carbs',
        'protein' => 'bg-macro-protein',
        'fat' => 'bg-macro-fat',
        'fiber' => 'bg-macro-fiber',
    ];
    $fill = $over ? 'bg-danger' : ($fills[$macro] ?? $fills['kcal']);

    $fmt = fn ($number) => \App\View\Format::number($number, $decimals);
    $withUnit = fn ($number) => trim($fmt($number).' '.($unit ?? ''));

    $caption = match (true) {
        ! $hasTarget => null,
        $over => __('common.over', ['value' => $withUnit($difference)]),
        $reached => __('common.done'),
        default => __('common.left', ['value' => $withUnit($difference)]),
    };
@endphp

<div {{ $attributes->class('min-w-0') }}>
    <div class="flex items-baseline justify-between gap-3">
        <span class="truncate text-sm font-medium text-ink">{{ $label }}</span>
        <span class="shrink-0 text-sm text-ink-muted">
            @if ($lowerBound)
                <span class="sr-only">{{ __('common.at_least') }}</span>
            @endif
            <span class="num" @if ($lowerBound) title="{{ __('common.at_least_hint') }}" @endif><span class="font-semibold {{ $over ? 'text-danger-ink' : 'text-ink' }}">{{ $lowerBound ? '≥ ' : '' }}{{ $fmt($value) }}</span>@if ($hasTarget) / {{ $fmt($target) }}@endif</span>
            @if ($unit)<span>{{ $unit }}</span>@endif
        </span>
    </div>

    <div
        class="relative mt-1.5 h-2 overflow-hidden rounded-full bg-track"
        role="progressbar"
        aria-label="{{ $label }}"
        aria-valuemin="0"
        aria-valuenow="{{ round($value, 2) }}"
        @if ($hasTarget) aria-valuemax="{{ round($target, 2) }}" @endif
        @if ($caption) aria-valuetext="{{ $withUnit($value) }} — {{ $caption }}" @endif
    >
        @if ($width > 0)
            <div class="h-full rounded-full transition-[width] duration-150 {{ $fill }}" style="width: {{ $width }}%"></div>
        @endif

        @if ($lowerBound && $width > 0 && $width < 100)
            {{-- hatched extension: the real value may be higher --}}
            <div
                class="absolute inset-y-0 w-4 opacity-50 {{ $fill }}"
                style="inset-inline-start: {{ $width }}%; mask-image: repeating-linear-gradient(90deg, #000 0 2px, transparent 2px 4px); -webkit-mask-image: repeating-linear-gradient(90deg, #000 0 2px, transparent 2px 4px);"
                aria-hidden="true"
            ></div>
        @endif

        @if ($targetTick !== null)
            <div class="absolute inset-y-0 w-0.5 bg-surface" style="inset-inline-start: {{ $targetTick }}%" aria-hidden="true"></div>
        @endif
    </div>

    @if ($remaining && $caption)
        <p class="mt-1 text-xs {{ $over ? 'font-medium text-danger-ink' : 'text-ink-muted' }}">{{ $caption }}</p>
    @endif
</div>
