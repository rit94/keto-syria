<?php

namespace App\Services\Ai\SmartLog;

use App\Services\Food\ArabicNormalizer;
use App\Services\Food\UnitResolver;

/**
 * Maps a unit word as spoken (Arabic, dialect or English) to the app's unit keys.
 * Only the word is translated here – whether the food actually has that unit, and how many
 * grams it weighs, is decided by UnitResolver from the database.
 */
final class UnitAliases
{
    private const ALIASES = [
        'g' => ['g', 'gr', 'gm', 'gram', 'grams', 'gramme', 'غ', 'غرام', 'غرامات', 'جرام', 'جرامات', 'غم', 'جم'],
        'kg' => ['kg', 'kilo', 'kilos', 'kilogram', 'kilograms', 'كغ', 'كيلو', 'كيلوغرام', 'كيلوجرام', 'كغم', 'كجم'],
        'oz' => ['oz', 'ounce', 'ounces', 'اونصه', 'اونصة'],
        'ouqiya' => ['ouqiya', 'ouqia', 'okiya', 'wa2iyeh', 'اوقيه', 'وقيه', 'اوقية', 'وقية', 'اواق', 'وقيات'],
        'piece' => ['piece', 'pieces', 'pc', 'pcs', 'unit', 'whole', 'حبه', 'حبة', 'حبات', 'قطعه', 'قطعة', 'قطع', 'وحده', 'واحده'],
        'large_piece' => ['large piece', 'large', 'حبه كبيره', 'كبيره'],
        'tbsp' => ['tbsp', 'tablespoon', 'tablespoons', 'ملعقه كبيره', 'ملعقه اكل', 'معلقه كبيره', 'معلقه اكل', 'ملاعق كبيره', 'ملعقه طعام'],
        'tsp' => ['tsp', 'teaspoon', 'teaspoons', 'ملعقه صغيره', 'ملعقه شاي', 'معلقه صغيره', 'معلقه شاي', 'ملاعق صغيره'],
        'cup' => ['cup', 'cups', 'glass', 'كاسه', 'كاسة', 'كاس', 'كوب', 'اكواب', 'كاسات'],
        'coffee_cup' => ['coffee cup', 'small coffee cup', 'فنجان', 'فناجين'],
        'slice' => ['slice', 'slices', 'شريحه', 'شرحه', 'شرحات', 'شرائح', 'شقفه'],
        'can' => ['can', 'cans', 'tin', 'علبه', 'علب'],
        'clove' => ['clove', 'cloves', 'سن', 'فص', 'اسنان', 'فصوص'],
        'leaf' => ['leaf', 'leaves', 'ورقه', 'اوراق', 'ورقات'],
        'bunch' => ['bunch', 'ضمه', 'جرزه', 'باقه'],
        'handful' => ['handful', 'كمشه', 'حفنه', 'قبضه'],
        'ball' => ['ball', 'balls', 'دعبوله', 'دعابيل', 'كره'],
        'loaf' => ['loaf', 'loaves', 'رغيف', 'ارغفه'],
        'half' => ['half', 'نص حبه', 'نصف حبه'],
        'skewer' => ['skewer', 'skewers', 'سيخ', 'اسياخ', 'سياخ'],
        'serving' => ['serving', 'servings', 'portion', 'حصه', 'حصص'],
    ];

    /** @var array<string, string>|null normalized alias => key */
    private static ?array $index = null;

    /** The unit key for a spoken unit, or the normalised input when it is not a known alias. */
    public static function canonical(string $unit): string
    {
        $key = UnitResolver::normalize($unit);
        if ($key === '' || isset(self::ALIASES[$key])) {
            return $key;
        }

        if (self::$index === null) {
            self::$index = [];
            foreach (self::ALIASES as $canonical => $aliases) {
                foreach ($aliases as $alias) {
                    self::$index[ArabicNormalizer::normalize($alias)] = $canonical;
                }
            }
        }

        return self::$index[ArabicNormalizer::normalize($unit)] ?? $key;
    }

    /** @return list<string> the keys the parser model is asked to use */
    public static function keys(): array
    {
        return array_keys(self::ALIASES);
    }
}
