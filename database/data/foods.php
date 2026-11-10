<?php

/**
 * Syrian keto food catalogue – one record per food, loaded by FoodCatalogueSeeder.
 *
 * Record keys (only `slug`, `ar`, `en`, `cat`, `n` are mandatory):
 *
 *   slug     stable unique id – never rename, the seeder upserts by it
 *   ar / en  display names (Syrian dialect first)
 *   cat      App\Enums\FoodCategory value
 *   state    App\Enums\FoodState value                      default 'raw'
 *   prep     App\Enums\Preparation value (drained/undrained) default null
 *   n        per 100 g EDIBLE portion:
 *            [kcal, protein g, TOTAL carbs g (fiber included), fiber g|null, fat g, saturated fat g|null]
 *            null = unknown. Never write 0 for a value that is merely unknown.
 *   yield    cooked weight / raw weight, only where a typical figure is well established
 *   parent   slug of the base food this row is a variant of (cooked / drained / cut)
 *   source   default 'USDA FoodData Central' (SR Legacy / Foundation values)
 *   ref      FDC description – only where it is known with confidence
 *   conf     App\Enums\Confidence value                      default 'high'
 *   est      true when the numbers are an estimate from an analogue (requires notes, conf ≠ high)
 *   notes    [notes_ar, notes_en]
 *   keto     false for foods that do not fit a keto day      default true
 *   aliases  dialect names, spelling variants, Latin transliterations
 *   units    household units in grams, built with $u(key, grams, isDefault, labelAr?, labelEn?)
 *   extra    extra_nutrients, e.g. ['polyols_g' => 100]
 *   exempt   reason the calories legitimately disagree with Atwater 4/4/9:
 *            'polyol'           – sugar alcohols counted as carbohydrate but ~0 kcal
 *            'specific_factors' – USDA food-specific energy factors / organic acids / unknown fibre
 *
 * The integrity rules (fiber ≤ carbs, saturated ≤ fat, macros ≤ 100 g, calories within
 * ±15 % of Atwater, …) are enforced by tests/Feature/Food/CatalogueIntegrityTest.php.
 */

return array_merge(
    require __DIR__.'/foods/produce.php',
    require __DIR__.'/foods/animal.php',
    require __DIR__.'/foods/dairy_fats.php',
    require __DIR__.'/foods/pantry.php',
);
