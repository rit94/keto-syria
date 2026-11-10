<?php

namespace Database\Seeders;

use App\Models\Food;
use App\Services\Food\FoodIndexer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads database/data/foods.php into the shared catalogue (foods.user_id = null).
 *
 * Idempotent: rows are upserted by slug, aliases / units / search_text are rebuilt to
 * match the data file, and ids of unchanged rows are preserved so log entries, recipes
 * and plans that reference catalogue foods stay valid. Foods removed from the data file
 * are left in place on purpose (they may be referenced by user data).
 */
class FoodCatalogueSeeder extends Seeder
{
    public const DEFAULT_SOURCE = 'USDA FoodData Central';

    public function run(FoodIndexer $indexer): void
    {
        $records = self::records();

        DB::transaction(function () use ($records, $indexer) {
            $foods = [];
            $existing = Food::query()->whereNull('user_id')->whereIn('slug', array_column($records, 'slug'))->get()->keyBy('slug');

            foreach ($records as $record) {
                $food = $existing[$record['slug']] ?? new Food;
                $isNew = ! $food->exists;
                $food->forceFill(self::attributes($record))->save();

                $indexer->sync($food, $record['aliases'], $isNew);
                $indexer->syncUnits($food, $record['units'], $isNew);

                $foods[$record['slug']] = $food;
            }

            // Second pass: variants can point at a parent defined later in the file.
            foreach ($records as $record) {
                $parentId = $record['parent'] === null ? null : $foods[$record['parent']]->id;
                $food = $foods[$record['slug']];
                if ($food->parent_food_id !== $parentId) {
                    $food->forceFill(['parent_food_id' => $parentId])->save();
                }
            }
        });
    }

    /**
     * The data file with every default filled in – also used by the integrity tests.
     *
     * @return list<array<string, mixed>>
     */
    public static function records(): array
    {
        $raw = require database_path('data/foods.php');
        $records = [];
        $slugs = array_column($raw, 'slug');

        foreach ($raw as $index => $row) {
            foreach (['slug', 'ar', 'en', 'cat', 'n'] as $key) {
                if (! isset($row[$key])) {
                    throw new RuntimeException("Food record #$index is missing [$key].");
                }
            }
            if (count($row['n']) !== 6) {
                throw new RuntimeException("Food [{$row['slug']}] must list six nutrient values.");
            }
            if (isset($row['parent']) && ! in_array($row['parent'], $slugs, true)) {
                throw new RuntimeException("Food [{$row['slug']}] has unknown parent [{$row['parent']}].");
            }

            $records[] = [
                'slug' => $row['slug'],
                'name_ar' => $row['ar'],
                'name_en' => $row['en'],
                'category' => $row['cat'],
                'state' => $row['state'] ?? 'raw',
                'preparation' => $row['prep'] ?? null,
                'calories' => (float) $row['n'][0],
                'protein_g' => (float) $row['n'][1],
                'carbs_g' => (float) $row['n'][2],
                'fiber_g' => $row['n'][3] === null ? null : (float) $row['n'][3],
                'fat_g' => (float) $row['n'][4],
                'saturated_fat_g' => $row['n'][5] === null ? null : (float) $row['n'][5],
                'extra_nutrients' => $row['extra'] ?? null,
                'cooked_yield' => $row['yield'] ?? null,
                'parent' => $row['parent'] ?? null,
                'source' => $row['source'] ?? self::DEFAULT_SOURCE,
                'source_ref' => $row['ref'] ?? null,
                'confidence' => $row['conf'] ?? 'high',
                'is_estimate' => $row['est'] ?? false,
                'notes_ar' => $row['notes'][0] ?? null,
                'notes_en' => $row['notes'][1] ?? null,
                'is_keto_friendly' => $row['keto'] ?? true,
                'aliases' => $row['aliases'] ?? [],
                'units' => $row['units'] ?? [],
                'exempt' => $row['exempt'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function attributes(array $record): array
    {
        return [
            'user_id' => null,
            'slug' => $record['slug'],
            'name_ar' => $record['name_ar'],
            'name_en' => $record['name_en'],
            'category' => $record['category'],
            'state' => $record['state'],
            'preparation' => $record['preparation'],
            'calories' => $record['calories'],
            'protein_g' => $record['protein_g'],
            'carbs_g' => $record['carbs_g'],
            'fiber_g' => $record['fiber_g'],
            'fat_g' => $record['fat_g'],
            'saturated_fat_g' => $record['saturated_fat_g'],
            'extra_nutrients' => $record['extra_nutrients'],
            'label_basis' => 'total',
            'cooked_yield' => $record['cooked_yield'],
            'source' => $record['source'],
            'source_ref' => $record['source_ref'],
            'confidence' => $record['confidence'],
            'is_estimate' => $record['is_estimate'],
            'notes_ar' => $record['notes_ar'],
            'notes_en' => $record['notes_en'],
            'is_keto_friendly' => $record['is_keto_friendly'],
        ];
    }
}
