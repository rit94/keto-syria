<?php

/**
 * Small builders shared by the catalogue partials. Returned as an array of closures
 * so every partial can `['u' => $u, 'est' => $est] = require __DIR__.'/_helpers.php';`.
 */
$labels = [
    'piece' => ['حبة', 'piece'],
    'tbsp' => ['ملعقة كبيرة', 'tablespoon'],
    'tsp' => ['ملعقة صغيرة', 'teaspoon'],
    'cup' => ['كاسة', 'cup'],
    'slice' => ['شريحة', 'slice'],
    'can' => ['علبة', 'can'],
    'clove' => ['سن', 'clove'],
    'leaf' => ['ورقة', 'leaf'],
    'bunch' => ['ضمة', 'bunch'],
    'handful' => ['كمشة', 'handful'],
    'ball' => ['دعبولة', 'ball'],
    'loaf' => ['رغيف', 'loaf'],
    'half' => ['نص حبة', 'half'],
    'coffee_cup' => ['فنجان', 'small coffee cup'],
];

return [
    /**
     * Household unit. $default marks the unit pre-selected in the UI.
     * Custom labels override the standard ones (e.g. to state a drained weight).
     */
    'u' => static function (string $key, float $grams, bool $default = false, ?string $ar = null, ?string $en = null) use ($labels): array {
        return [
            'unit' => $key,
            'label_ar' => $ar ?? $labels[$key][0],
            'label_en' => $en ?? $labels[$key][1],
            'grams' => $grams,
            'is_default' => $default,
        ];
    },

    /**
     * Notes for an estimated entry: the specific remark followed by the standard
     * "varies by producer – prefer the package label" advice in both languages.
     *
     * @return array{0: string, 1: string} [notes_ar, notes_en]
     */
    'est' => static function (string $ar, string $en): array {
        return [
            $ar.' القيم تقديرية وتختلف حسب المنتج أو اللحّام؛ الأدق أن تضيف مكوّناً خاصاً من ملصق العبوة إن وُجد.',
            $en.' Values are an estimate and vary by producer or butcher; a custom ingredient entered from the package label is more accurate.',
        ];
    },
];
