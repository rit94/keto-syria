<?php

namespace App\View;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Display formatting for the UI. Always emits Western digits (0-9) in both locales.
 *
 * Blade: @num($value, $decimals = 0)  @date($date, $format = 'medium')
 */
class Format
{
    /** Carbon locale per app locale (Levantine month names for Arabic). */
    public const CARBON_LOCALES = ['ar' => 'ar_SY', 'en' => 'en'];

    private const DATE_FORMATS = [
        'short' => 'D MMM',              // 5 Jan
        'medium' => 'D MMMM YYYY',       // 5 January 2026
        'long' => 'dddd D MMMM YYYY',    // Monday 5 January 2026
        'weekday' => 'dddd D MMMM',      // Monday 5 January
        'day' => 'dd',                   // Mo
        'month' => 'MMMM YYYY',
        'time' => 'H:mm',
        'numeric' => 'YYYY-MM-DD',
    ];

    /**
     * Format a number with Western digits. Trailing zeros are trimmed unless $fixed is true.
     * Null renders as an em dash (unknown value).
     */
    public static function number(int|float|string|null $value, int $decimals = 0, bool $fixed = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $formatted = number_format((float) $value, $decimals, '.', ',');

        if (! $fixed && $decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * Localized date. $format is a preset name (short, medium, long, weekday, day, month, time, numeric)
     * or any Carbon isoFormat pattern.
     */
    public static function date(CarbonInterface|string|null $date, string $format = 'medium'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return self::westernDigits(
            $date->locale(self::carbonLocale())->isoFormat(self::DATE_FORMATS[$format] ?? $format)
        );
    }

    /** "Today" / "Yesterday" / "Tomorrow", otherwise the weekday preset. */
    public static function relativeDay(CarbonInterface|string $date): string
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return match (true) {
            $date->isToday() => __('common.today'),
            $date->isYesterday() => __('common.yesterday'),
            $date->isTomorrow() => __('common.tomorrow'),
            default => self::date($date, 'weekday'),
        };
    }

    /** Replace Arabic-Indic and Persian digits with Western digits. */
    public static function westernDigits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => ',',
        ]);
    }

    public static function carbonLocale(?string $locale = null): string
    {
        return self::CARBON_LOCALES[$locale ?? app()->getLocale()] ?? 'en';
    }

    /** Text direction for a locale (defaults to the current one). */
    public static function dir(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? 'rtl' : 'ltr';
    }
}
