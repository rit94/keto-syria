<?php

return [
    'attributes' => [
        'name_ar' => 'Arabic name',
        'name_en' => 'English name',
        'category' => 'category',
        'state' => 'state',
        'preparation' => 'preparation',
        'calories' => 'calories',
        'protein_g' => 'protein',
        'carbs_g' => 'carbohydrate',
        'fiber_g' => 'fiber',
        'fat_g' => 'fat',
        'saturated_fat_g' => 'saturated fat',
        'label_basis' => 'carbohydrate basis',
        'per_grams' => 'reference amount',
        'cooked_yield' => 'cooked yield',
        'is_estimate' => 'estimate flag',
        'is_keto_friendly' => 'keto-friendly flag',
        'notes_ar' => 'Arabic notes',
        'notes_en' => 'English notes',
        'aliases' => 'other names',
        'alias' => 'other name',
        'units' => 'units',
        'unit_unit' => 'unit key',
        'unit_label_ar' => 'Arabic unit name',
        'unit_label_en' => 'English unit name',
        'unit_grams' => 'unit weight in grams',
        'unit_is_default' => 'default unit flag',
    ],

    'errors' => [
        'over_100' => 'The :attribute cannot be more than 100 g per 100 g.',
        'calories_too_high' => 'Calories cannot be more than about 900 kcal per 100 g (that is pure fat).',
        'fiber_exceeds_carbs' => 'Fiber cannot be more than the total carbohydrate. If the label lists carbohydrate without fiber, choose the "net" basis.',
        'saturated_exceeds_fat' => 'Saturated fat cannot be more than the total fat.',
        'macros_exceed_100' => 'Protein, carbohydrate and fat together cannot be more than 100 g per 100 g.',
        'per_grams_partial' => 'To change the reference amount, enter calories, protein, carbohydrate and fat again.',
        'catalogue_readonly' => 'Catalogue foods cannot be changed. Create your own ingredient instead.',
        'in_use' => 'This ingredient is used in :count recipe. Remove it from the recipe first.|This ingredient is used in :count recipes. Remove it from them first.',
    ],

    'warnings' => [
        'calories_mismatch' => 'The calories (:calories kcal) do not match the macros, which give about :expected kcal. Please double-check the label values.',
    ],
];
