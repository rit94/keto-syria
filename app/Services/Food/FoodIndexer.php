<?php

namespace App\Services\Food;

use App\Models\Food;

/**
 * Keeps the derived search data of a food (alias rows, search_text, household units)
 * in step with its names. Shared by the catalogue seeder and CustomFoodService so
 * both produce rows the search service can rank identically.
 */
class FoodIndexer
{
    public const TERM_SEPARATOR = ' | ';

    /**
     * Replace the food's aliases with the given list (rows that are unchanged keep
     * their ids) and rebuild search_text.
     *
     * @param  iterable<string>  $aliases  raw alias strings, any spelling
     * @param  bool  $isNew  the food was inserted just now, so there are no rows to reconcile
     */
    public function sync(Food $food, iterable $aliases, bool $isNew = false): void
    {
        $nameTerms = array_filter([
            ArabicNormalizer::normalize((string) $food->name_ar),
            ArabicNormalizer::normalize((string) $food->name_en),
        ]);

        $wanted = [];
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            $normalized = ArabicNormalizer::normalize($alias);
            if ($normalized === '' || in_array($normalized, $nameTerms, true) || isset($wanted[$normalized])) {
                continue;
            }
            $wanted[$normalized] = [
                'alias' => $alias,
                'normalized' => $normalized,
                'locale' => ArabicNormalizer::isArabic($alias) ? 'ar' : 'en',
            ];
        }

        $existing = $isNew ? $food->aliases()->getRelated()->newCollection() : $food->aliases()->get()->keyBy('normalized');
        foreach ($existing as $normalized => $row) {
            if (! isset($wanted[$normalized])) {
                $row->delete();
            }
        }
        $new = [];
        foreach ($wanted as $normalized => $attributes) {
            if ($existing->has($normalized)) {
                $existing[$normalized]->update($attributes);
            } else {
                $new[] = ['food_id' => $food->id, ...$attributes];
            }
        }
        if ($new !== []) {
            $food->aliases()->insert($new);
        }

        $terms = array_values(array_unique([...$nameTerms, ...array_keys($wanted)]));
        $food->forceFill(['search_text' => implode(self::TERM_SEPARATOR, $terms)])->save();
        $food->unsetRelation('aliases');
    }

    /**
     * Replace the food's household units.
     *
     * @param  iterable<array{unit: string, label_ar: string, label_en: string, grams: float|int, is_default?: bool}>  $units
     */
    public function syncUnits(Food $food, iterable $units, bool $isNew = false): void
    {
        $wanted = [];
        $hasDefault = false;
        foreach ($units as $unit) {
            $isDefault = ! $hasDefault && ! empty($unit['is_default']);
            $hasDefault = $hasDefault || $isDefault;
            $wanted[$unit['unit']] = [
                'unit' => $unit['unit'],
                'label_ar' => $unit['label_ar'],
                'label_en' => $unit['label_en'],
                'grams' => (float) $unit['grams'],
                'is_default' => $isDefault,
            ];
        }

        $existing = $isNew ? $food->units()->getRelated()->newCollection() : $food->units()->get()->keyBy('unit');
        foreach ($existing as $key => $row) {
            if (! isset($wanted[$key])) {
                $row->delete();
            }
        }
        $new = [];
        foreach ($wanted as $key => $attributes) {
            if ($existing->has($key)) {
                $existing[$key]->update($attributes);
            } else {
                $new[] = ['food_id' => $food->id, ...$attributes];
            }
        }
        if ($new !== []) {
            $food->units()->insert($new);
        }
        $food->unsetRelation('units');
    }

    /**
     * Split a stored search_text back into its normalized terms.
     *
     * @return list<string>
     */
    public static function terms(?string $searchText): array
    {
        if ($searchText === null || $searchText === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $searchText)), static fn (string $t) => $t !== ''));
    }
}
