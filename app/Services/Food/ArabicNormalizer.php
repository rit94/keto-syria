<?php

namespace App\Services\Food;

/**
 * Folds Arabic (and Latin) text into a canonical form for matching food names.
 * It is deliberately lossy: the output is only ever used for search / comparison,
 * never shown to the user.
 */
final class ArabicNormalizer
{
    /** Letters that are spelled interchangeably in everyday Syrian writing. */
    private const LETTER_MAP = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ی' => 'ي', 'ے' => 'ي',
        'ة' => 'ه', 'ۃ' => 'ه',
        'ؤ' => 'و',
        'ئ' => 'ي',
        'ک' => 'ك', 'گ' => 'ك',
        'چ' => 'ج', 'پ' => 'ب', 'ڤ' => 'ف',
        // Arabic-Indic and Persian digits
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        // tatweel
        'ـ' => '',
    ];

    /**
     * Strip tashkeel + tatweel, unify hamza/alef forms, ى→ي, ة→ه, ؤ→و, ئ→ي,
     * convert Arabic-Indic digits, lowercase Latin, turn punctuation into spaces,
     * collapse whitespace and trim.
     */
    public static function normalize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Decompose first (when ext-intl is available) so Latin accents become combining
        // marks too; Arabic hamza carriers decompose to the same letters LETTER_MAP yields.
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = is_string($decomposed) ? $decomposed : $text;
        }

        // Combining marks: Arabic tashkeel (fatha, damma, kasra, shadda, sukun, tanween,
        // dagger alef ...) and decomposed accents.
        $text = preg_replace('/\p{M}+/u', '', $text) ?? $text;
        $text = strtr($text, self::LETTER_MAP);
        $text = mb_strtolower($text, 'UTF-8');
        // Anything that is not a letter, a digit or whitespace becomes a separator.
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Remove a leading definite article "ال" from every token of an already
     * normalized string ("الزهره المسلوقه" → "زهره مسلوقه"). Tokens that would
     * become shorter than two letters are left alone.
     */
    public static function stripArticle(string $normalized): string
    {
        if ($normalized === '') {
            return '';
        }

        $tokens = array_map(
            static fn (string $token): string => str_starts_with($token, 'ال') && mb_strlen($token, 'UTF-8') >= 4
                ? mb_substr($token, 2, null, 'UTF-8')
                : $token,
            explode(' ', $normalized),
        );

        return implode(' ', $tokens);
    }

    /** normalize() followed by stripArticle(): the form used for loose matching. */
    public static function forMatching(string $text): string
    {
        return self::stripArticle(self::normalize($text));
    }

    /**
     * Tokens of the loose matching form.
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $normalized = self::forMatching($text);

        return $normalized === '' ? [] : array_values(array_unique(explode(' ', $normalized)));
    }

    public static function isArabic(string $text): bool
    {
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }
}
