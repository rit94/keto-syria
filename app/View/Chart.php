<?php

namespace App\View;

/**
 * Geometry helpers for the dependency-free <x-chart.line> / <x-chart.bars> components.
 * Presentation only — values are plotted as given, never recalculated.
 */
class Chart
{
    /**
     * Normalise chart input to a list of ['label' => string, 'value' => ?float].
     *
     * Accepts a list of numbers, a label => value map, or a list of ['label' => …, 'value' => …].
     *
     * @param  array<int|string, mixed>  $points
     * @param  list<string>|null  $labels
     * @return list<array{label: string, value: ?float}>
     */
    public static function series(array $points, ?array $labels = null): array
    {
        $series = [];
        $isList = array_is_list($points);
        $index = 0;

        foreach ($points as $key => $point) {
            if (is_array($point)) {
                $label = (string) ($point['label'] ?? ($labels[$index] ?? ''));
                $value = $point['value'] ?? null;
            } else {
                $label = (string) ($labels[$index] ?? ($isList ? '' : $key));
                $value = $point;
            }

            $series[] = ['label' => $label, 'value' => is_numeric($value) ? (float) $value : null];
            $index++;
        }

        return $series;
    }

    /**
     * A "nice" axis for the given values: [min, max, ticks].
     *
     * @param  list<float>  $values
     * @return array{0: float, 1: float, 2: list<float>}
     */
    public static function axis(array $values, bool $fromZero = false, ?float $min = null, ?float $max = null): array
    {
        $values = array_values(array_filter($values, fn ($value) => $value !== null));

        $lo = $min ?? ($fromZero ? 0.0 : ($values ? min($values) : 0.0));
        $hi = $max ?? ($values ? max($values) : 1.0);

        if ($hi <= $lo) {
            $pad = abs($hi) > 0 ? abs($hi) * 0.1 : 1.0;
            $hi += $pad;
            $lo = $fromZero || $min !== null ? $lo : $lo - $pad;
        }

        $step = self::niceStep(($hi - $lo) / 2);

        $start = $min ?? floor($lo / $step) * $step;
        $end = $start;
        $ticks = [$start];

        while ($end < $hi - 1e-9 && count($ticks) < 6) {
            $end += $step;
            $ticks[] = round($end, 6);
        }

        return [(float) $start, (float) $end, $ticks];
    }

    /** Position of a value on the axis as a 0–100 percentage (0 = axis minimum). */
    public static function percent(float $value, float $min, float $max): float
    {
        if ($max <= $min) {
            return 0.0;
        }

        return round(max(0.0, min(100.0, ($value - $min) / ($max - $min) * 100)), 2);
    }

    /** Decimals needed to print the axis ticks without losing information (0–2). */
    public static function tickDecimals(array $ticks): int
    {
        $decimals = 0;

        foreach ($ticks as $tick) {
            $fraction = rtrim(rtrim(number_format(abs($tick - floor($tick)), 2, '.', ''), '0'), '.');
            $decimals = max($decimals, max(0, strlen($fraction) - 2));
        }

        return $decimals;
    }

    /**
     * Indexes of the x labels to print so they do not collide (first and last always shown).
     *
     * @return list<int>
     */
    public static function labelIndexes(int $count, int $max = 6): array
    {
        if ($count <= $max) {
            return $count > 0 ? range(0, $count - 1) : [];
        }

        $indexes = [];

        for ($i = 0; $i < $max; $i++) {
            $indexes[] = (int) round($i * ($count - 1) / ($max - 1));
        }

        return array_values(array_unique($indexes));
    }

    private static function niceStep(float $rough): float
    {
        if ($rough <= 0) {
            return 1.0;
        }

        $magnitude = 10 ** floor(log10($rough));
        $fraction = $rough / $magnitude;

        $nice = match (true) {
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 2.5 => 2.5,
            $fraction <= 5 => 5,
            default => 10,
        };

        return $nice * $magnitude;
    }
}
