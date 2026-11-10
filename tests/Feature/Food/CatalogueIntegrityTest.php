<?php

use App\Domain\Nutrition\Nutrients;
use App\Enums\Confidence;
use App\Enums\FoodCategory;
use App\Enums\FoodState;
use App\Enums\Preparation;
use App\Models\Food;
use App\Services\Food\ArabicNormalizer;
use App\Services\Food\FoodSearchService;
use Database\Seeders\FoodCatalogueSeeder;

/**
 * Guards the quality of database/data/foods.php. Every rule here applies to the whole
 * catalogue, so a bad row fails with its slug in the message.
 */
function catalogueRecords(): array
{
    static $records;

    return $records ??= FoodCatalogueSeeder::records();
}

function nutrientsOf(array $r): Nutrients
{
    return new Nutrients($r['calories'], $r['protein_g'], $r['carbs_g'], $r['fiber_g'], $r['fat_g'], $r['saturated_fat_g']);
}

it('has a sensible number of foods with unique slugs', function () {
    $slugs = array_column(catalogueRecords(), 'slug');

    expect(count($slugs))->toBeGreaterThanOrEqual(130)->toBeLessThanOrEqual(200)
        ->and(array_values(array_unique($slugs)))->toBe($slugs);

    foreach ($slugs as $slug) {
        expect($slug)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/');
    }
});

it('uses only valid enum values and complete names', function () {
    foreach (catalogueRecords() as $r) {
        $slug = $r['slug'];

        expect(FoodCategory::tryFrom($r['category']))->not->toBeNull("$slug: category")
            ->and(FoodState::tryFrom($r['state']))->not->toBeNull("$slug: state")
            ->and(Confidence::tryFrom($r['confidence']))->not->toBeNull("$slug: confidence")
            ->and(trim($r['name_ar']))->not->toBe('', "$slug: name_ar")
            ->and(trim($r['name_en']))->not->toBe('', "$slug: name_en")
            ->and(ArabicNormalizer::isArabic($r['name_ar']))->toBeTrue("$slug: name_ar must be Arabic")
            ->and(trim($r['source']))->not->toBe('', "$slug: source");

        if ($r['preparation'] !== null) {
            expect(Preparation::tryFrom($r['preparation']))->not->toBeNull("$slug: preparation");
        }
        if ($r['cooked_yield'] !== null) {
            expect($r['cooked_yield'])->toBeGreaterThan(0.3, "$slug: cooked_yield")->toBeLessThan(1.0, "$slug: cooked_yield");
            expect($r['state'])->toBe('raw', "$slug: only raw foods carry a cooked_yield");
        }
    }
});

it('keeps every row physically plausible', function () {
    foreach (catalogueRecords() as $r) {
        $slug = $r['slug'];

        foreach (['calories', 'protein_g', 'carbs_g', 'fat_g'] as $key) {
            expect($r[$key])->toBeGreaterThanOrEqual(0.0, "$slug: $key");
        }
        expect($r['calories'])->toBeLessThanOrEqual(902.0, "$slug: calories")
            ->and($r['protein_g'] + $r['carbs_g'] + $r['fat_g'])->toBeLessThanOrEqual(100.0 + 1e-9, "$slug: protein + carbs + fat > 100 g");

        if ($r['fiber_g'] !== null) {
            expect($r['fiber_g'])->toBeGreaterThanOrEqual(0.0, "$slug: fiber")
                ->toBeLessThanOrEqual($r['carbs_g'], "$slug: fiber > carbs");
        }
        if ($r['saturated_fat_g'] !== null) {
            expect($r['saturated_fat_g'])->toBeGreaterThanOrEqual(0.0, "$slug: saturated fat")
                ->toBeLessThanOrEqual($r['fat_g'], "$slug: saturated fat > fat");
        }
    }
});

it('has calories within 15% of the Atwater estimate unless explicitly exempt', function () {
    $allowedExemptions = ['polyol', 'specific_factors'];

    foreach (catalogueRecords() as $r) {
        $slug = $r['slug'];
        $atwater = nutrientsOf($r)->atwaterCalories();
        $difference = abs($r['calories'] - $atwater);

        if ($r['exempt'] !== null) {
            expect(in_array($r['exempt'], $allowedExemptions, true))->toBeTrue("$slug: unknown exemption");
            // An exemption must be needed, and the food-specific-factor kind stays small in absolute terms.
            expect($difference)->toBeGreaterThan(0.15 * $atwater, "$slug: exemption is not needed");
            if ($r['exempt'] === 'specific_factors') {
                expect($difference)->toBeLessThanOrEqual(10.0, "$slug: too far from Atwater for a specific-factor exemption");
            }
            if ($r['exempt'] === 'polyol') {
                expect($r['extra_nutrients']['polyols_g'] ?? 0)->toBeGreaterThan(0, "$slug: polyol exemption needs polyols_g");
            }

            continue;
        }

        // Near-zero drinks (coffee, tea) are stored as whole kilocalories; allow that rounding.
        $tolerance = max(0.15 * $atwater, 1.0);
        expect($difference)->toBeLessThanOrEqual($tolerance, sprintf('%s: %.1f kcal vs Atwater %.1f', $slug, $r['calories'], $atwater));
    }
});

it('gives every food at least one alias beyond its own names', function () {
    foreach (catalogueRecords() as $r) {
        $names = [ArabicNormalizer::normalize($r['name_ar']), ArabicNormalizer::normalize($r['name_en'])];
        $extra = array_filter(
            array_map(ArabicNormalizer::normalize(...), $r['aliases']),
            fn (string $alias) => $alias !== '' && ! in_array($alias, $names, true),
        );

        expect(count($extra))->toBeGreaterThanOrEqual(1, "{$r['slug']}: needs a dialect / spelling alias");

        $arabic = array_filter($r['aliases'], ArabicNormalizer::isArabic(...));
        expect(count($arabic))->toBeGreaterThanOrEqual(1, "{$r['slug']}: needs an Arabic alias");
    }
});

it('documents every estimate honestly', function () {
    $estimates = 0;

    foreach (catalogueRecords() as $r) {
        $slug = $r['slug'];

        if ($r['is_estimate']) {
            $estimates++;
            expect($r['confidence'])->not->toBe('high', "$slug: an estimate cannot be high confidence")
                ->and(trim((string) $r['notes_ar']))->not->toBe('', "$slug: estimate needs notes_ar")
                ->and(trim((string) $r['notes_en']))->not->toBe('', "$slug: estimate needs notes_en")
                ->and($r['source'])->toStartWith('Estimate', "$slug: estimate source must say so")
                ->and($r['source_ref'])->toBeNull("$slug: an estimate has no exact source reference");
        } else {
            expect($r['source'])->not->toStartWith('Estimate', "$slug: source says estimate but is_estimate is false");
        }

        // Notes always come in both languages.
        expect($r['notes_ar'] === null)->toBe($r['notes_en'] === null, "$slug: notes must exist in both languages");
    }

    expect($estimates)->toBeGreaterThanOrEqual(20);
});

it('defines clean household units', function () {
    foreach (catalogueRecords() as $r) {
        $slug = $r['slug'];
        $keys = array_column($r['units'], 'unit');

        expect(array_values(array_unique($keys)))->toBe($keys, "$slug: duplicate unit keys")
            ->and(count(array_filter($r['units'], fn (array $u) => $u['is_default'])))->toBeLessThanOrEqual(1, "$slug: more than one default unit");

        foreach ($r['units'] as $unit) {
            expect($unit['unit'])->toMatch('/^[a-z][a-z0-9_]*$/', "$slug: unit key")
                ->and(in_array($unit['unit'], ['g', 'kg', 'oz', 'ouqiya'], true))->toBeFalse("$slug: reserved unit key")
                ->and((float) $unit['grams'])->toBeGreaterThan(0.0, "$slug: unit grams")
                ->and(trim($unit['label_ar']))->not->toBe('', "$slug: unit label_ar")
                ->and(trim($unit['label_en']))->not->toBe('', "$slug: unit label_en");
        }
    }
});

it('links variants to an existing base food without chains', function () {
    $bySlug = array_column(catalogueRecords(), null, 'slug');

    foreach (catalogueRecords() as $r) {
        if ($r['parent'] === null) {
            continue;
        }
        expect($bySlug)->toHaveKey($r['parent'])
            ->and($r['parent'])->not->toBe($r['slug'])
            ->and($bySlug[$r['parent']]['parent'])->toBeNull("{$r['slug']}: parent [{$r['parent']}] is itself a variant");
    }
});

it('provides drained and undrained rows for canned and preserved foods', function () {
    $bySlug = array_column(catalogueRecords(), null, 'slug');

    foreach ([
        'tuna-oil-drained' => 'drained', 'tuna-oil-undrained' => 'undrained', 'tuna-water-drained' => 'drained',
        'sardines-oil-drained' => 'drained', 'sardines-oil-undrained' => 'undrained',
        'olives-green' => 'drained', 'olives-green-oil' => 'undrained',
        'makdous-drained' => 'drained', 'makdous-undrained' => 'undrained',
    ] as $slug => $preparation) {
        expect($bySlug[$slug]['preparation'])->toBe($preparation, $slug);
    }

    // Keeping the oil must never make a food leaner.
    expect($bySlug['tuna-oil-undrained']['fat_g'])->toBeGreaterThan($bySlug['tuna-oil-drained']['fat_g'])
        ->and($bySlug['sardines-oil-undrained']['fat_g'])->toBeGreaterThan($bySlug['sardines-oil-drained']['fat_g'])
        ->and($bySlug['makdous-undrained']['fat_g'])->toBeGreaterThan($bySlug['makdous-drained']['fat_g']);
});

it('flags the non-keto staples and keeps the keto staples friendly', function () {
    $bySlug = array_column(catalogueRecords(), null, 'slug');

    foreach (['bread-arabic', 'rice-raw', 'rice-cooked', 'bulgur-raw', 'potato-raw', 'sugar', 'dates', 'chickpeas-cooked', 'lentils-cooked', 'cashews', 'pomegranate-molasses'] as $slug) {
        expect($bySlug[$slug]['is_keto_friendly'])->toBeFalse($slug);
    }
    foreach (['egg-whole-raw', 'olive-oil', 'ghee-animal', 'halloumi', 'cauliflower-raw', 'avocado', 'walnuts', 'erythritol'] as $slug) {
        expect($bySlug[$slug]['is_keto_friendly'])->toBeTrue($slug);
    }
});

it('finds every required food by its Syrian dialect name', function () {
    $this->seed(FoodCatalogueSeeder::class);
    $search = app(FoodSearchService::class);

    // query => slug that must be among the first 5 results
    $required = [
        'زهرة' => 'cauliflower-raw', 'قرنبيط' => 'cauliflower-raw', 'بانجان' => 'eggplant-raw', 'باذنجان' => 'eggplant-raw',
        'كوسا' => 'zucchini-raw', 'أفوكادو' => 'avocado',
        'بيض' => 'egg-whole-raw', 'صفار البيض' => 'egg-yolk-raw', 'بياض البيض' => 'egg-white-raw', 'بيض مسلوق' => 'egg-boiled', 'بيض مقلي' => 'egg-fried',
        'لحمة كباب' => 'kebab-mince-regular', 'شرحات عجل' => 'beef-slices-lean-raw', 'لحم غنم' => 'lamb-leg-lean-raw',
        'دهنة' => 'lamb-fat-raw', 'لية' => 'lamb-tail-fat-raw', 'دهنة مرق العضم' => 'bone-broth-fat', 'مرق عظم' => 'bone-broth',
        'مشللة' => 'mshallaleh', 'حلوم' => 'halloumi', 'جبنة عكاوي' => 'akkawi', 'جبنة بلدية' => 'white-cheese-baladi', 'جبنة بيضا' => 'white-cheese-baladi',
        'شنكليش' => 'shanklish', 'قريشة' => 'qarisheh', 'فطر' => 'mushroom-raw',
        'طون' => 'tuna-oil-drained', 'طون بالماء' => 'tuna-water-drained', 'طون مع زيته' => 'tuna-oil-undrained',
        'سردين' => 'sardines-oil-drained', 'سردين مع زيته' => 'sardines-oil-undrained',
        'لبنة' => 'labneh', 'لبنة مدعبلة بزيت' => 'labneh-balls-oil', 'لبن' => 'yogurt-whole', 'قشطة' => 'qashta-baladi',
        'سمنة عربي' => 'ghee-animal', 'سمنة حيواني' => 'ghee-animal', 'سمنة نباتي' => 'ghee-vegetable', 'زبدة' => 'butter',
        'زيت زيتون' => 'olive-oil', 'زيتون أخضر' => 'olives-green', 'زيتون أسود' => 'olives-black', 'طحينة' => 'tahini',
        'مكدوس' => 'makdous-drained', 'مكدوس مع زيته' => 'makdous-undrained',
        'صدر فروج' => 'chicken-breast-raw', 'فخذ فروج' => 'chicken-thigh-skin-raw', 'وردة' => 'chicken-thigh-skin-raw', 'جوانح' => 'chicken-wings-raw',
        'صدر فروج مع جلد' => 'chicken-breast-skin-raw', 'وردة بدون جلد' => 'chicken-thigh-raw',
        'كبدة' => 'lamb-liver-raw', 'سودة' => 'lamb-liver-raw', 'نخاعات' => 'lamb-brain-raw', 'معلاق' => 'lamb-pluck-raw',
        'سجق' => 'sujuk', 'نقانق' => 'naqaniq', 'مرتديلا' => 'mortadella', 'سمك' => 'fish-tilapia-raw', 'سلطان ابراهيم' => 'fish-red-mullet-raw',
        'خيار' => 'cucumber', 'بندورة' => 'tomato', 'خس' => 'lettuce', 'سبانخ' => 'spinach-raw', 'سلق' => 'chard', 'ملفوف' => 'cabbage',
        'بقدونس' => 'parsley', 'نعنع' => 'mint-fresh', 'جرجير' => 'arugula', 'بقلة' => 'purslane', 'فليفلة' => 'bell-pepper-green',
        'فاصوليا خضرا' => 'green-beans', 'لوبية' => 'green-beans', 'بامية' => 'okra', 'بصل' => 'onion', 'توم' => 'garlic',
        'ليمون' => 'lemon', 'حامض' => 'lemon', 'فجل' => 'radish', 'هليون' => 'asparagus', 'بروكلي' => 'broccoli',
        'جوز' => 'walnuts', 'لوز' => 'almonds', 'فستق حلبي' => 'pistachios', 'بندق' => 'hazelnuts', 'صنوبر' => 'pine-nuts',
        'بزر دوار الشمس' => 'sunflower-seeds', 'بزر قرع' => 'pumpkin-seeds', 'سمسم' => 'sesame', 'كاجو' => 'cashews',
        'فريز' => 'strawberry', 'دبس رمان' => 'pomegranate-molasses', 'دبس فليفلة' => 'pepper-paste', 'سماق' => 'sumac', 'زعتر' => 'zaatar-mix',
        'قهوة' => 'coffee', 'شاي' => 'tea', 'متة' => 'mate', 'ستيفيا' => 'stevia', 'إريثريتول' => 'erythritol',
        'خبز عربي' => 'bread-arabic', 'رز' => 'rice-raw', 'برغل' => 'bulgur-raw', 'بطاطا' => 'potato-raw', 'سكر' => 'sugar',
        'تمر' => 'dates', 'حمص' => 'chickpeas-cooked', 'عدس' => 'lentils-cooked',
    ];

    $failures = [];
    foreach ($required as $query => $slug) {
        $found = $search->search($query, null, 5)->pluck('slug')->all();
        if (! in_array($slug, $found, true)) {
            $failures[] = "$query → expected $slug, got [".implode(', ', $found).']';
        }
    }

    expect($failures)->toBe([]);
    expect(Food::query()->whereNull('user_id')->count())->toBe(count(catalogueRecords()));
});
