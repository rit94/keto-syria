{{--
    Circular progress for one macro: value against a daily target.

    Values come from the nutrition engine (DailySummary->totals / ->targets / ->remaining).
    This component only formats and draws them.

    Props
      label       string        macro name (required)
      value       float         amount consumed
      target      float|null    daily target; null draws an empty track ("no target")
      unit        string|null   e.g. __('common.units.g')
      macro       kcal|carbs|protein|fat|fiber   colour series (default kcal)
      size        sm|md|lg      80 / 112 / 160 px (default md)
      limit       bool          true (default): going over the target is an over-target state
                                (danger colour + "over" caption) — carbs, calories.
                                false: the target is a goal; reaching it is fine — protein, fiber.
      lowerBound  bool          value is a minimum because some foods lack this nutrient: shows "≥"
      decimals    int           decimals for value/target (default 0)
      left        float|null    remaining amount from the engine (RemainingMacros; negative = over). Pass it
                                when you have it; otherwise target − value is displayed.

    Usage
      <x-macro-ring :label="__('common.macros.net_carbs')" macro="carbs"
                    :value="$summary->totals->netCarbs()" :target="$summary->targets?->netCarbs"
                    :unit="__('common.units.g')" :lowerBound="! $summary->totals->isComplete()" />
--}}
@props([
    'label',
    'value' => 0,
    'target' => null,
    'unit' => null,
    'macro' => 'kcal',
    'size' => 'md',
    'limit' => true,
    'lowerBound' => false,
    'decimals' => 0,
    'left' => null,
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

    $radius = 42;
    $circumference = 2 * M_PI * $radius;
    $dash = round(min(max($ratio, 0), 1) * $circumference, 2);

    $strokes = [
        'kcal' => 'stroke-macro-kcal',
        'carbs' => 'stroke-macro-carbs',
        'protein' => 'stroke-macro-protein',
        'fat' => 'stroke-macro-fat',
        'fiber' => 'stroke-macro-fiber',
    ];
    $stroke = $over ? 'stroke-danger' : ($strokes[$macro] ?? $strokes['kcal']);

    $sizes = [
        'sm' => ['size-20', 'text-base', 'text-[0.6875rem]', 9],
        'md' => ['size-28', 'text-2xl', 'text-xs', 8],
        'lg' => ['size-40', 'text-4xl', 'text-sm', 7],
    ];
    [$box, $valueText, $smallText, $strokeWidth] = $sizes[$size] ?? $sizes['md'];

    $fmt = fn ($number) => \App\View\Format::number($number, $decimals);
    $withUnit = fn ($number) => trim($fmt($number).' '.($unit ?? ''));

    $caption = match (true) {
        ! $hasTarget => __('common.no_target'),
        $over => __('common.over', ['value' => $withUnit($difference)]),
        $reached => __('common.done'),
        default => __('common.left', ['value' => $withUnit($difference)]),
    };

    $aria = $label.': '.($lowerBound ? __('common.at_least').' ' : '').$withUnit($value)
        .($hasTarget ? ' '.__('common.of_target', ['target' => $withUnit($target)]).' — '.$caption : '');
@endphp

<div {{ $attributes->class('flex flex-col items-center text-center') }} role="img" aria-label="{{ $aria }}">
    <div class="relative {{ $box }}">
        <svg viewBox="0 0 100 100" class="size-full -rotate-90" aria-hidden="true">
            <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="{{ $strokeWidth }}" class="stroke-track" />
            @if ($dash > 0)
                <circle
                    cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="{{ $strokeWidth }}" stroke-linecap="round"
                    stroke-dasharray="{{ $dash }} {{ round($circumference, 2) }}"
                    class="{{ $stroke }} transition-[stroke-dasharray] duration-150"
                />
            @endif
        </svg>

        <div class="absolute inset-0 flex flex-col items-center justify-center" aria-hidden="true">
            <span class="num font-semibold leading-none {{ $valueText }} {{ $over ? 'text-danger-ink' : 'text-ink' }}">{{ $lowerBound ? '≥' : '' }}{{ $fmt($value) }}</span>
            @if ($hasTarget && $size !== 'sm')
                <span class="num mt-1 leading-none text-ink-muted {{ $smallText }}">/ {{ $fmt($target) }}</span>
            @endif
        </div>
    </div>

    <p class="mt-2 text-sm font-medium leading-tight text-ink" aria-hidden="true">{{ $label }}</p>
    <p class="mt-0.5 leading-tight {{ $smallText }} {{ $over ? 'font-medium text-danger-ink' : 'text-ink-muted' }}" aria-hidden="true">{{ $caption }}</p>
</div>
