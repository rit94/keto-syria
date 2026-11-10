<?php

use App\Models\Food;
use App\Models\FoodAlias;
use App\Models\FoodUnit;
use App\Models\User;
use App\Services\Food\ArabicNormalizer;
use App\Services\Food\FoodIndexer;
use Database\Seeders\FoodCatalogueSeeder;

function catalogueSnapshot(): array
{
    return [
        'foods' => Food::query()->orderBy('id')->get()->map(fn (Food $f) => collect($f->getAttributes())->except(['created_at', 'updated_at'])->all())->all(),
        'aliases' => FoodAlias::query()->orderBy('id')->get()->map->getAttributes()->all(),
        'units' => FoodUnit::query()->orderBy('id')->get()->map->getAttributes()->all(),
    ];
}

it('seeds the whole catalogue with aliases, units and search text', function () {
    $this->seed(FoodCatalogueSeeder::class);

    $records = FoodCatalogueSeeder::records();

    expect(Food::count())->toBe(count($records))
        ->and(Food::whereNotNull('user_id')->count())->toBe(0)
        ->and(Food::whereNull('search_text')->orWhere('search_text', '')->count())->toBe(0)
        ->and(FoodAlias::count())->toBeGreaterThan(count($records) * 3)
        ->and(FoodUnit::count())->toBeGreaterThan(count($records));

    $egg = Food::where('slug', 'egg-whole-raw')->with('units', 'aliases')->firstOrFail();

    expect($egg->name_ar)->toBe('بيض كامل (ني)')
        ->and($egg->calories)->toBe(143.0)
        ->and($egg->protein_g)->toBe(12.56)
        ->and($egg->source)->toBe('USDA FoodData Central')
        ->and($egg->confidence->value)->toBe('high')
        ->and($egg->is_estimate)->toBeFalse()
        ->and($egg->units->firstWhere('unit', 'piece')->grams)->toBe(50.0)
        ->and($egg->units->firstWhere('unit', 'piece')->is_default)->toBeTrue()
        ->and($egg->aliases->pluck('normalized')->all())->toContain('بيض', 'بيضه', 'eggs')
        ->and(FoodIndexer::terms($egg->search_text))->toContain('بيض كامل ني', 'egg whole raw', 'بيض');
});

it('stores unknown fiber and saturated fat as null, never zero', function () {
    $this->seed(FoodCatalogueSeeder::class);

    $purslane = Food::where('slug', 'purslane')->firstOrFail();
    $makdous = Food::where('slug', 'makdous-drained')->firstOrFail();

    expect($purslane->fiber_g)->toBeNull()
        ->and($purslane->per100g()->isMissing('fiber'))->toBeTrue()
        ->and($makdous->saturated_fat_g)->toBeNull()
        ->and($makdous->is_estimate)->toBeTrue()
        ->and($makdous->confidence->value)->toBe('low')
        ->and($makdous->preparation->value)->toBe('drained');
});

it('links variants to their parent food', function () {
    $this->seed(FoodCatalogueSeeder::class);

    $raw = Food::where('slug', 'chicken-breast-raw')->firstOrFail();
    $cooked = Food::where('slug', 'chicken-breast-cooked')->firstOrFail();

    expect($cooked->parent_food_id)->toBe($raw->id)
        ->and($cooked->state->value)->toBe('cooked')
        ->and($raw->cooked_yield)->toBe(0.75)
        ->and($raw->variants->pluck('slug')->all())->toContain('chicken-breast-cooked', 'chicken-breast-skin-raw');
});

it('is idempotent: a second run changes nothing', function () {
    $this->seed(FoodCatalogueSeeder::class);
    $first = catalogueSnapshot();

    $this->seed(FoodCatalogueSeeder::class);
    $second = catalogueSnapshot();

    expect($second)->toEqual($first);
});

it('repairs drifted rows on re-run while keeping their ids', function () {
    $this->seed(FoodCatalogueSeeder::class);

    $labneh = Food::where('slug', 'labneh')->firstOrFail();
    $id = $labneh->id;
    $unitIds = $labneh->units()->orderBy('id')->pluck('id', 'unit')->all();

    $labneh->forceFill(['calories' => 999, 'name_ar' => 'غلط', 'search_text' => 'x', 'parent_food_id' => Food::where('slug', 'butter')->value('id')])->save();
    $labneh->aliases()->create(['alias' => 'stray', 'normalized' => 'stray', 'locale' => 'en']);
    $labneh->aliases()->where('normalized', ArabicNormalizer::normalize('لبنة'))->delete();
    $labneh->units()->where('unit', 'tbsp')->update(['grams' => 500]);
    $labneh->units()->create(['unit' => 'bucket', 'label_ar' => 'سطل', 'label_en' => 'bucket', 'grams' => 5000]);

    $this->seed(FoodCatalogueSeeder::class);

    $labneh = Food::where('slug', 'labneh')->with('aliases', 'units')->firstOrFail();

    expect($labneh->id)->toBe($id)
        ->and($labneh->calories)->toBe(140.0)
        ->and($labneh->name_ar)->toBe('لبنة كاملة الدسم')
        ->and($labneh->parent_food_id)->toBeNull()
        ->and($labneh->aliases->pluck('normalized')->all())->toContain('لبنه', 'labneh')->not->toContain('stray')
        ->and($labneh->units->pluck('unit')->all())->not->toContain('bucket')
        ->and($labneh->units->firstWhere('unit', 'tbsp')->grams)->toBe(15.0)
        ->and($labneh->units()->orderBy('id')->pluck('id', 'unit')->all())->toBe($unitIds)
        ->and(FoodIndexer::terms($labneh->search_text))->toContain('لبنه');
});

it('never touches custom foods of users', function () {
    $user = User::factory()->create();
    $custom = Food::factory()->create(['user_id' => $user->id, 'name_ar' => 'لبنة بيتي', 'slug' => null, 'calories' => 111]);

    $this->seed(FoodCatalogueSeeder::class);
    $this->seed(FoodCatalogueSeeder::class);

    expect($custom->fresh()->calories)->toBe(111.0)
        ->and($custom->fresh()->user_id)->toBe($user->id)
        ->and(Food::whereNotNull('user_id')->count())->toBe(1);
});

it('is called by the database seeder', function () {
    $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

    expect($source)->toContain('FoodCatalogueSeeder::class');
});
