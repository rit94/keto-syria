{{--
    Line chart (weight trend, daily net carbs…). No JS, no dependencies: an SVG polyline in a
    CSS-sized frame with HTML axis labels. In RTL the time axis runs right-to-left and the
    value axis sits on the right — it follows the nearest [dir], nothing to configure.

    Props
      points    array   the series, oldest first. Any of:
                          [82.4, 82.1, null, 81.9]                       (null = gap)
                          ['Sat' => 82.4, 'Sun' => 82.1]                 (label => value)
                          [['label' => 'Sat', 'value' => 82.4], …]
      labels    array|null   x labels when points is a plain list
      target    float|null   horizontal dashed reference line
      unit      string|null  unit shown in the legend / accessible text
      macro     kcal|carbs|protein|fat|fiber|null   line colour; null = accent
      height    int      plot height in px (default 160)
      decimals  int|null decimals for axis and values (default: derived from the axis)
      area      bool     soft fill under the line (default true)
      min, max  float|null   force the value axis bounds
      title     string|null  accessible name (required for a11y when there is no visible heading)

    Usage
      <x-chart.line :points="$weights" :target="$profile->goal_weight_kg" :unit="__('common.units.kg')" :title="__('progress.weight_trend')" />
--}}
@props([
    'points' => [],
    'labels' => null,
    'target' => null,
    'unit' => null,
    'macro' => null,
    'height' => 160,
    'decimals' => null,
    'area' => true,
    'min' => null,
    'max' => null,
    'title' => null,
])

@php
    use App\View\Chart;
    use App\View\Format;

    $series = Chart::series($points, $labels);
    $values = array_values(array_filter(array_column($series, 'value'), fn ($v) => $v !== null));
    $count = count($series);
    $target = $target === null ? null : (float) $target;

    [$axisMin, $axisMax, $ticks] = Chart::axis($target === null ? $values : [...$values, $target], false, $min, $max);
    $decimals ??= Chart::tickDecimals($ticks);

    // Plot coordinates in a 0–100 box; y is inset so strokes are not clipped at the edges.
    $x = fn (int $i) => $count > 1 ? round($i / ($count - 1) * 100, 2) : 50.0;
    $y = fn (float $v) => round(96 - Chart::percent($v, $axisMin, $axisMax) * 0.92, 2);

    $line = '';
    $areas = [];
    $segment = [];
    foreach ($series as $i => $point) {
        if ($point['value'] === null) {
            if (count($segment) > 1) {
                $areas[] = $segment;
            }
            $segment = [];

            continue;
        }
        $line .= ($segment ? ' L' : ' M').$x($i).' '.$y($point['value']);
        $segment[] = [$x($i), $y($point['value'])];
    }
    if (count($segment) > 1) {
        $areas[] = $segment;
    }

    $lastIndex = null;
    foreach ($series as $i => $point) {
        if ($point['value'] !== null) {
            $lastIndex = $i;
        }
    }

    $colors = [
        'kcal' => 'text-macro-kcal',
        'carbs' => 'text-macro-carbs',
        'protein' => 'text-macro-protein',
        'fat' => 'text-macro-fat',
        'fiber' => 'text-macro-fiber',
    ];
    $color = $colors[$macro] ?? 'text-accent';

    $fmt = fn ($number) => Format::number($number, $decimals);
    $withUnit = fn ($number) => trim($fmt($number).' '.($unit ?? ''));
    $labelIndexes = Chart::labelIndexes($count);
    $hasLabels = collect($series)->contains(fn ($p) => $p['label'] !== '');

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
                    <span class="num absolute end-0 -translate-y-1/2 leading-none" style="top: {{ $y($tick) }}%">{{ $fmt($tick) }}</span>
                @endforeach
            </div>

            <div class="min-w-0 flex-1">
                <div class="relative" style="height: {{ (int) $height }}px">
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible rtl:-scale-x-100 {{ $color }}">
                        @foreach ($ticks as $tick)
                            <line x1="0" x2="100" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" class="stroke-line" stroke-width="1" vector-effect="non-scaling-stroke" />
                        @endforeach

                        @if ($target !== null)
                            <line x1="0" x2="100" y1="{{ $y($target) }}" y2="{{ $y($target) }}" class="stroke-ink-subtle" stroke-width="1.5" stroke-dasharray="5 4" vector-effect="non-scaling-stroke" />
                        @endif

                        @if ($area)
                            @foreach ($areas as $segment)
                                <path
                                    d="M{{ $segment[0][0] }} 96 @foreach ($segment as [$px, $py]) L{{ $px }} {{ $py }} @endforeach L{{ $segment[count($segment) - 1][0] }} 96 Z"
                                    fill="currentColor" fill-opacity="0.09" stroke="none"
                                />
                            @endforeach
                        @endif

                        <path d="{{ trim($line) }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />

                        {{-- points: zero-length round-capped segments stay circular under non-uniform scaling --}}
                        @if ($count <= 31)
                            @foreach ($series as $i => $point)
                                @if ($point['value'] !== null)
                                    <path
                                        d="M{{ $x($i) }} {{ $y($point['value']) }} h0.001"
                                        fill="none" stroke="currentColor" stroke-linecap="round"
                                        stroke-width="{{ $i === $lastIndex ? 9 : 5 }}" vector-effect="non-scaling-stroke"
                                    />
                                @endif
                            @endforeach
                        @elseif ($lastIndex !== null)
                            <path d="M{{ $x($lastIndex) }} {{ $y($series[$lastIndex]['value']) }} h0.001" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="9" vector-effect="non-scaling-stroke" />
                        @endif
                    </svg>
                </div>

                @if ($hasLabels)
                    <div class="relative mt-1.5 h-4 text-xs text-ink-subtle">
                        @foreach ($labelIndexes as $i)
                            <span
                                @class([
                                    'absolute top-0 whitespace-nowrap leading-none',
                                    'ltr:-translate-x-1/2 rtl:translate-x-1/2' => $i !== 0 && $i !== $count - 1,
                                    'ltr:-translate-x-full rtl:translate-x-full' => $i === $count - 1 && $count > 1,
                                ])
                                style="inset-inline-start: {{ $x($i) }}%"
                            >{{ $series[$i]['label'] }}</span>
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
