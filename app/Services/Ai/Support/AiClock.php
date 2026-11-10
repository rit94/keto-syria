<?php

namespace App\Services\Ai\Support;

use Carbon\CarbonImmutable;
use Throwable;

/** Dates for the AI layer: "today" is the user's day in Syria (config ai.timezone). */
final class AiClock
{
    public static function timezone(): string
    {
        return (string) config('ai.timezone', 'Asia/Damascus');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /** Midnight of the current diary day, as a plain date (no timezone shifting afterwards). */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::now()->toDateString());
    }

    /**
     * Strict 'Y-m-d' (or 'today' / 'yesterday' / 'tomorrow') → date. Null / '' → today.
     * Returns null when the value is not a valid date.
     */
    public static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return self::today();
        }
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));
        $relative = ['today' => 0, 'yesterday' => -1, 'tomorrow' => 1];
        if (isset($relative[$value])) {
            return self::today()->addDays($relative[$value]);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date !== null && $date->format('Y-m-d') === $value ? CarbonImmutable::parse($value) : null;
    }
}
