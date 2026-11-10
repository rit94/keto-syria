{{--
    Bar chart (calories per day, net carbs per day…). No JS, no dependencies. Bars start at
    zero. In RTL the first bar is on the right and the value axis sits on the right — it
    follows the nearest [dir], nothing to configure.

    Props
      points    array   oldest first. Any of:
                          [1650, 1720, null, 1580]                       (null = no data that day)
                          ['Sat' => 1650, 'Sun' => 1720]                 (label => value)
                          [['label' => 'Sat', 'value' => 1650], …]
      labels    array|null   x labels when points is a plain list
      target    float|null   horizontal dashed reference line
      limit     bool     true (default): bars above the target use the over-target (danger) colour
      unit      string|null
      macro     kcal|carbs|protein|fat|fiber|null   bar colour; null = accent
      height    int      plot height in px (default 160)
      decimals  int|null
      max       float|null   force the top of the value axis
      highlight int|null     index of a bar to emphasise (e.g. today); others are slightly muted
      title     string|null  accessible name

    Usage
      <x-chart.bars :points="$netCarbsByDay" :target="$targets->netCarbs" macro="carbs" :unit="__('common.units.g')"
                    :highlight="count($netCarbsByDay) - 1" :title="__('progress.net_carbs_week')" />
--}}
@props([
    'points' => [],
    'labels' => null,
    'target' => null,
    'limit' => true,
    'unit' => null,
    'macro' => null,
    'height' => 160,
    'decimals' => null,
    'max' => null,
    'highlight' => null,
    'title' => null,
])

@php
    use App\View\Chart;
    use App\View\Format;

    $series = Chart::series($points, $labels);
    $values = array_values(array_filter(array_column($series, 'value'), fn ($v) => $v !== null));
    $count = count($series);
    $target = $target === null ? null : (float) $target;

    [$axisMin, $axisMax, $ticks] = Chart::axis($target === null ? $values : [...$values, $target], true, 0.0, $max);
    $decimals ??= Chart::tickDecimals($ticks);

    $fills = [
        'kcal' => 'bg-macro-kcal',
        'carbs' => 'bg-macro-carbs',
        'protein' => 'bg-macro-protein',
        'fat' => 'bg-macro-fat',
        'fiber' => 'bg-macro-fiber',
    ];
    $fill = $fills[$macro] ?? 'bg-accent';

    $fmt = fn ($number) => Format::number($number, $decimals);
    $withUnit = fn ($number) => trim($fmt($number).' '.($unit ?? ''));
    $labelIndexes = Chart::labelIndexes($count, 8);
    $hasLabels = collect($series)->contains(fn ($p) => $p['label'] !== '');
    $gap = $count > 20 ? 'gap-px' : ($count > 10 ? 'gap-1' : 'gap-2');

    $summary = $values
        ? __('common.chart.range', ['min' => $withUnit(min($values)), 'max' => $withUnit(max($values))])
        : __('common.chart.empty');
@endphp

<figure {{ $attributes->class('min-w-0') }} aria-label="{{ trim(($title ? $title.'. ' : '').$summary) }}">
    @if (! $values)
        <div class="flex items-center justify-center rounded-control bg-surface-2 px-4 text-center text-sm text-ink-muted" style="height: {{ (int) $height }}px">
            {{ __('common.chart.empty') }}
        </div>
    @else
        <div class="flex gap-2" aria-hidden="true">
            {{-- value axis (inline start) --}}
            <div class="relative w-9 shrink-0 text-xs text-ink-subtle" style="height: {{ (int) $height }}px">
                @foreach ($ticks as $tick)
                    <span class="num absolute end-0 translate-y-1/2 leading-none" style="bottom: {{ Chart::percent($tick, $axisMin, $axisMax) }}%">{{ $fmt($tick) }}</span>
                @endforeach
            </div>

            <div class="min-w-0 flex-1">
                <div class="relative" style="height: {{ (int) $height }}px">
                    @foreach ($ticks as $tick)
                        <div class="absolute inset-x-0 border-t border-line" style="bottom: {{ Chart::percent($tick, $axisMin, $axisMax) }}%"></div>
                    @endforeach

                    <div class="absolute inset-0 flex items-end {{ $gap }}">
                        @foreach ($series as $i => $point)
                            @php
                                $isOver = $limit && $target !== null && $point['value'] !== null && $point['value'] > $target;
                                $barHeight = $point['value'] === null ? 0 : Chart::percent($point['value'], $axisMin, $axisMax);
                            @endphp
                            <div class="flex h-full min-w-0 flex-1 items-end justify-center">
                                @if ($point['value'] !== null)
                                    <div
                                        @class([
                                            'w-full max-w-10 rounded-t-[5px]',
                                            $isOver ? 'bg-danger' : $fill,
                                            'opacity-55' => $highlight !== null && $i !== (int) $highlight,
                                        ])
                                        style="height: {{ $barHeight }}%; min-height: 2px"
                                    ></div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($target !== null)
                        <div class="absolute inset-x-0 border-t-[1.5px] border-dashed border-ink-subtle" style="bottom: {{ Chart::percent($target, $axisMin, $axisMax) }}%"></div>
                    @endif
                </div>

                @if ($hasLabels)
                    <div class="mt-1.5 flex {{ $gap }} text-xs text-ink-subtle">
                        @foreach ($series as $i => $point)
                            <span @class(['min-w-0 flex-1 overflow-visible whitespace-nowrap text-center leading-none', 'font-semibold text-ink' => $highlight !== null && $i === (int) $highlight])>
                                @if (in_array($i, $labelIndexes, true)){{ $point['label'] }}@endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @if ($target !== null)
            <figcaption class="mt-2 flex items-center gap-2 text-xs text-ink-muted" aria-hidden="true">
                <span class="w-5 border-t-[1.5px] border-dashed border-ink-subtle"></span>
                {{ __('common.chart.target_line', ['value' => $withUnit($target)]) }}
            </figcaption>
        @endif
    @endif

    <table class="sr-only">
        @if ($title)<caption>{{ $title }}</caption>@endif
        @foreach ($series as $i => $point)
            <tr><th scope="row">{{ $point['label'] !== '' ? $point['label'] : $i + 1 }}</th><td>{{ $point['value'] === null ? '—' : $withUnit($point['value']) }}</td></tr>
        @endforeach
    </table>
</figure>
