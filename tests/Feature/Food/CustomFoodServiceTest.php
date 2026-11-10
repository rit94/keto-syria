<?php

use App\Enums\CarbBasis;
use App\Enums\Confidence;
use App\Enums\FoodCategory;
use App\Enums\FoodState;
use App\Models\Food;
use App\Models\User;
use App\Services\Food\CustomFoodService;
use App\Services\Food\FoodIndexer;
use App\Services\Food\FoodInUseException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(CustomFoodService::class);
    $this->user = User::factory()->create();
});

function labelData(array $overrides = []): array
{
    return [...[
        'name_ar' => 'لبنة المراعي',
        'name_en' => 'Almarai labneh',
        'category' => 'dairy',
        'calories' => 160,
        'protein_g' => 7,
        'carbs_g' => 5,
        'fiber_g' => 0,
        'fat_g' => 12.5,
        'saturated_fat_g' => 8,
    ], ...$overrides];
}

function validationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

describe('create', function () {
    it('stores a private food owned by the user with label provenance', function () {
        $food = $this->service->create($this->user, labelData());

        expect($food->exists)->toBeTrue()
            ->and($food->user_id)->toBe($this->user->id)
            ->and($food->isCustom())->toBeTrue()
            ->and($food->slug)->toBeNull()
            ->and($food->name_ar)->toBe('لبنة المراعي')
            ->and($food->name_en)->toBe('Almarai labneh')
            ->and($food->category)->toBe(FoodCategory::Dairy)
            ->and($food->state)->toBe(FoodState::NotApplicable)
            ->and($food->calories)->toBe(160.0)
            ->and($food->carbs_g)->toBe(5.0)
            ->and($food->fat_g)->toBe(12.5)
            ->and($food->source)->toBe('User label')
            ->and($food->is_estimate)->toBeFalse()
            ->and($food->confidence)->toBe(Confidence::High)
            ->and($food->label_basis)->toBe(CarbBasis::Total)
            ->and($food->is_keto_friendly)->toBeTrue()
            ->and($this->service->warnings())->toBe([]);
    });

    it('marks a user estimate as such', function () {
        $food = $this->service->create($this->user, labelData(['is_estimate' => true]));

        expect($food->source)->toBe('User estimate')
            ->and($food->is_estimate)->toBeTrue()
            ->and($food->confidence)->toBe(Confidence::Low);
    });

    it('builds search text and aliases', function () {
        $food = $this->service->create($this->user, labelData(['aliases' => ['لبنة السوبرماركت', 'لبنة المراعي', '  ', 'Labneh Tub']]));

        expect($food->aliases->pluck('normalized')->all())->toBe(['لبنه السوبرماركت', 'labneh tub'])
            ->and($food->aliases->pluck('locale')->all())->toBe(['ar', 'en'])
            ->and(FoodIndexer::terms($food->search_text))->toBe(['لبنه المراعي', 'almarai labneh', 'لبنه السوبرماركت', 'labneh tub']);
    });

    it('stores optional household units with a single default', function () {
        $food = $this->service->create($this->user, labelData(['units' => [
            ['unit' => 'tbsp', 'label_ar' => 'ملعقة كبيرة', 'label_en' => 'tablespoon', 'grams' => 15, 'is_default' => true],
            ['unit' => 'tub', 'label_ar' => 'علبة', 'grams' => '400', 'is_default' => true],
        ]]));

        $units = $food->units->keyBy('unit');

        expect($units)->toHaveCount(2)
            ->and($units['tbsp']->grams)->toBe(15.0)
            ->and($units['tbsp']->is_default)->toBeTrue()
            ->and($units['tub']->grams)->toBe(400.0)
            ->and($units['tub']->label_en)->toBe('علبة')
            ->and($units['tub']->is_default)->toBeFalse();
    });

    it('keeps unknown fiber and saturated fat as null', function () {
        $food = $this->service->create($this->user, labelData(['fiber_g' => null, 'saturated_fat_g' => '']));
        $bare = $this->service->create($this->user, ['name_ar' => 'شي', 'calories' => 100, 'protein_g' => 10, 'carbs_g' => 5, 'fat_g' => 4]);

        expect($food->fiber_g)->toBeNull()
            ->and($food->saturated_fat_g)->toBeNull()
            ->and($bare->fiber_g)->toBeNull()
            ->and($bare->per100g()->isMissing('fiber'))->toBeTrue();
    });

    it('falls back to the English name when no Arabic name is given', function () {
        $food = $this->service->create($this->user, labelData(['name_ar' => null, 'name_en' => 'Protein bar']));

        expect($food->name_ar)->toBe('Protein bar')
            ->and($food->name_en)->toBe('Protein bar');
    });

    it('accepts enum instances as well as their values', function () {
        $food = $this->service->create($this->user, labelData(['category' => FoodCategory::Cheese, 'state' => FoodState::Raw, 'label_basis' => CarbBasis::Net]));

        expect($food->category)->toBe(FoodCategory::Cheese)
            ->and($food->state)->toBe(FoodState::Raw)
            ->and($food->label_basis)->toBe(CarbBasis::Net);
    });
});

describe('carbohydrate basis and reference amount', function () {
    it('converts a net-carb label to total carbs and records the basis', function () {
        $food = $this->service->create($this->user, [
            'name_ar' => 'خبز كيتو', 'calories' => 240, 'protein_g' => 22, 'carbs_g' => 4, 'fiber_g' => 18, 'fat_g' => 12,
            'label_basis' => 'net',
        ]);

        expect($food->carbs_g)->toBe(22.0)
            ->and($food->fiber_g)->toBe(18.0)
            ->and($food->label_basis)->toBe(CarbBasis::Net)
            ->and($food->per100g()->netCarbs())->toBe(4.0);
    });

    it('leaves a total-carb label untouched', function () {
        $food = $this->service->create($this->user, [
            'name_ar' => 'خبز كيتو', 'calories' => 240, 'protein_g' => 22, 'carbs_g' => 22, 'fiber_g' => 18, 'fat_g' => 12,
            'label_basis' => 'total',
        ]);

        expect($food->carbs_g)->toBe(22.0)
            ->and($food->label_basis)->toBe(CarbBasis::Total)
            ->and($food->per100g()->netCarbs())->toBe(4.0);
    });

    it('treats a net label with unknown fiber as carbs = net (nothing to add)', function () {
        $food = $this->service->create($this->user, labelData(['carbs_g' => 6, 'fiber_g' => null, 'label_basis' => 'net']));

        expect($food->carbs_g)->toBe(6.0)
            ->and($food->fiber_g)->toBeNull()
            ->and($food->label_basis)->toBe(CarbBasis::Net);
    });

    it('rejects fiber above carbs on a total basis but accepts the same numbers on a net basis', function () {
        $data = ['name_ar' => 'بزر قطونا', 'calories' => 200, 'protein_g' => 2, 'carbs_g' => 5, 'fiber_g' => 80, 'fat_g' => 1];

        expect(validationErrors(fn () => $this->service->create($this->user, [...$data, 'label_basis' => 'total'])))->toHaveKey('fiber_g');

        $food = $this->service->create($this->user, [...$data, 'label_basis' => 'net']);

        expect($food->carbs_g)->toBe(85.0)->and($food->fiber_g)->toBe(80.0);
    });

    it('scales per-serving label values to per 100 g', function () {
        $food = $this->service->create($this->user, [
            'name_ar' => 'لوح بروتين', 'per_grams' => 40, 'label_basis' => 'net',
            'calories' => 160, 'protein_g' => 12, 'carbs_g' => 2, 'fiber_g' => 6, 'fat_g' => 10, 'saturated_fat_g' => 4,
        ]);

        expect($food->calories)->toEqualWithDelta(400.0, 1e-6)
            ->and($food->protein_g)->toEqualWithDelta(30.0, 1e-6)
            ->and($food->carbs_g)->toEqualWithDelta(20.0, 1e-6)
            ->and($food->fiber_g)->toEqualWithDelta(15.0, 1e-6)
            ->and($food->fat_g)->toEqualWithDelta(25.0, 1e-6)
            ->and($food->saturated_fat_g)->toEqualWithDelta(10.0, 1e-6);
    });
});

describe('validation', function () {
    it('requires a name and the four main values', function () {
        $errors = validationErrors(fn () => $this->service->create($this->user, []));

        expect($errors)->toHaveKeys(['name_ar', 'calories', 'protein_g', 'carbs_g', 'fat_g'])
            ->and(Food::count())->toBe(0);
    });

    it('rejects malformed input', function (array $overrides, string $errorKey) {
        expect(validationErrors(fn () => $this->service->create($this->user, labelData($overrides))))->toHaveKey($errorKey);
        expect(Food::count())->toBe(0);
    })->with([
        'blank name' => [['name_ar' => '   ', 'name_en' => null], 'name_ar'],
        'non-numeric calories' => [['calories' => 'كتير'], 'calories'],
        'negative protein' => [['protein_g' => -1], 'protein_g'],
        'negative fiber' => [['fiber_g' => -0.5], 'fiber_g'],
        'protein above 100' => [['protein_g' => 120, 'carbs_g' => 0, 'fat_g' => 0], 'protein_g'],
        'calories above pure fat' => [['calories' => 1200], 'calories'],
        'saturated above fat' => [['saturated_fat_g' => 20], 'saturated_fat_g'],
        'macros above 100 g' => [['protein_g' => 40, 'carbs_g' => 40, 'fat_g' => 30], 'macros'],
        'unknown category' => [['category' => 'dessert'], 'category'],
        'unknown basis' => [['label_basis' => 'gross'], 'label_basis'],
        'zero reference amount' => [['per_grams' => 0], 'per_grams'],
        'unit without grams' => [['units' => [['unit' => 'cup', 'label_ar' => 'كاسة']]], 'units.0.grams'],
        'unit with zero grams' => [['units' => [['unit' => 'cup', 'label_ar' => 'كاسة', 'grams' => 0]]], 'units.0.grams'],
        'reserved unit key' => [['units' => [['unit' => 'kg', 'label_ar' => 'كيلو', 'grams' => 1000]]], 'units.0.unit'],
        'duplicate unit keys' => [['units' => [['unit' => 'cup', 'label_ar' => 'كاسة', 'grams' => 200], ['unit' => 'cup', 'label_ar' => 'كاسة', 'grams' => 250]]], 'units.0.unit'],
        'unit without a label' => [['units' => [['unit' => 'cup', 'grams' => 200]]], 'units.0.label_ar'],
    ]);

    it('checks the macro sum after the net → total conversion', function () {
        $errors = validationErrors(fn () => $this->service->create($this->user, [
            'name_ar' => 'غلط', 'calories' => 400, 'protein_g' => 30, 'carbs_g' => 40, 'fiber_g' => 25, 'fat_g' => 10, 'label_basis' => 'net',
        ]));

        expect($errors)->toHaveKey('macros');
    });

    it('translates its messages', function () {
        app()->setLocale('ar');
        $ar = validationErrors(fn () => $this->service->create($this->user, labelData(['saturated_fat_g' => 20])));

        app()->setLocale('en');
        $en = validationErrors(fn () => $this->service->create($this->user, labelData(['saturated_fat_g' => 20])));

        expect($ar['saturated_fat_g'][0])->toContain('الدهون المشبعة')
            ->and($en['saturated_fat_g'][0])->toContain('Saturated fat');
    });
});

describe('calorie warning', function () {
    it('warns, without failing, when calories differ from Atwater by more than 20%', function () {
        // 7 P + 5 C + 12.5 F → 160.5 kcal by Atwater; the label claims 260.
        $food = $this->service->create($this->user, labelData(['calories' => 260]));
        $warnings = $this->service->warnings();

        expect($food->exists)->toBeTrue()
            ->and($food->calories)->toBe(260.0)
            ->and($warnings)->toHaveCount(1)
            ->and($warnings[0]['code'])->toBe('calories_mismatch')
            ->and($warnings[0]['expected'])->toEqualWithDelta(160.5, 1e-6)
            ->and($warnings[0]['message'])->toBeString()->not->toBe('');
    });

    it('stays quiet inside the tolerance and resets between calls', function () {
        $this->service->create($this->user, labelData(['calories' => 260]));
        expect($this->service->warnings())->toHaveCount(1);

        $this->service->create($this->user, labelData(['calories' => 185]));
        expect($this->service->warnings())->toBe([]);
    });

    it('does not nag about tiny absolute differences', function () {
        $this->service->create($this->user, ['name_ar' => 'مشروب دايت', 'calories' => 4, 'protein_g' => 0, 'carbs_g' => 0.2, 'fat_g' => 0]);

        expect($this->service->warnings())->toBe([]);
    });

    it('can check data without saving', function () {
        $warnings = $this->service->check(labelData(['calories' => 20]));

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0]['code'])->toBe('calories_mismatch')
            ->and(Food::count())->toBe(0);

        expect(fn () => $this->service->check(labelData(['fat_g' => -1])))->toThrow(ValidationException::class);
    });
});

describe('update', function () {
    it('changes only the given fields and rebuilds the search text', function () {
        $food = $this->service->create($this->user, labelData(['aliases' => ['لبنة السوبرماركت']]));

        $updated = $this->service->update($food, ['name_ar' => 'لبنة بوك', 'fat_g' => 14]);

        expect($updated->id)->toBe($food->id)
            ->and($updated->name_ar)->toBe('لبنة بوك')
            ->and($updated->name_en)->toBe('Almarai labneh')
            ->and($updated->fat_g)->toBe(14.0)
            ->and($updated->protein_g)->toBe(7.0)
            ->and($updated->calories)->toBe(160.0)
            ->and($updated->user_id)->toBe($this->user->id)
            ->and($updated->aliases->pluck('alias')->all())->toBe(['لبنة السوبرماركت'])
            ->and(FoodIndexer::terms($updated->search_text))->toBe(['لبنه بوك', 'almarai labneh', 'لبنه السوبرماركت']);
    });

    it('replaces aliases and units only when they are supplied', function () {
        $food = $this->service->create($this->user, labelData([
            'aliases' => ['قديم'],
            'units' => [['unit' => 'tbsp', 'label_ar' => 'ملعقة كبيرة', 'grams' => 15]],
        ]));

        $kept = $this->service->update($food, ['calories' => 165]);
        expect($kept->aliases->pluck('alias')->all())->toBe(['قديم'])
            ->and($kept->units->pluck('unit')->all())->toBe(['tbsp']);

        $replaced = $this->service->update($food, ['aliases' => ['جديد'], 'units' => []]);
        expect($replaced->aliases->pluck('alias')->all())->toBe(['جديد'])
            ->and($replaced->units)->toBeEmpty();
    });

    it('does not re-apply the net conversion to stored total carbs', function () {
        $food = $this->service->create($this->user, [
            'name_ar' => 'خبز كيتو', 'calories' => 240, 'protein_g' => 22, 'carbs_g' => 4, 'fiber_g' => 18, 'fat_g' => 12, 'label_basis' => 'net',
        ]);

        $renamed = $this->service->update($food, ['name_ar' => 'خبز كيتو أسمر']);
        expect($renamed->carbs_g)->toBe(22.0)
            ->and($renamed->label_basis)->toBe(CarbBasis::Net);

        $reentered = $this->service->update($food, ['carbs_g' => 6, 'label_basis' => 'net']);
        expect($reentered->carbs_g)->toBe(24.0)
            ->and($reentered->fiber_g)->toBe(18.0);

        $asTotal = $this->service->update($food, ['carbs_g' => 20, 'label_basis' => 'total']);
        expect($asTotal->carbs_g)->toBe(20.0)
            ->and($asTotal->label_basis)->toBe(CarbBasis::Total);
    });

    it('validates the merged result', function () {
        $food = $this->service->create($this->user, labelData());

        expect(validationErrors(fn () => $this->service->update($food, ['saturated_fat_g' => 13])))->toHaveKey('saturated_fat_g')
            ->and(validationErrors(fn () => $this->service->update($food, ['name_ar' => '', 'name_en' => ''])))->toHaveKey('name_ar')
            ->and(validationErrors(fn () => $this->service->update($food, ['per_grams' => 30, 'calories' => 50])))->toHaveKey('per_grams')
            ->and($food->fresh()->saturated_fat_g)->toBe(8.0);
    });

    it('re-evaluates the calorie warning and the estimate flag', function () {
        $food = $this->service->create($this->user, labelData());

        $updated = $this->service->update($food, ['calories' => 400, 'is_estimate' => true]);

        expect($this->service->warnings())->toHaveCount(1)
            ->and($updated->source)->toBe('User estimate')
            ->and($updated->confidence)->toBe(Confidence::Low);
    });

    it('refuses to change a catalogue food', function () {
        $catalogue = Food::factory()->create(['user_id' => null, 'slug' => 'butter-test', 'calories' => 717]);

        expect(fn () => $this->service->update($catalogue, ['calories' => 1]))->toThrow(AuthorizationException::class)
            ->and($catalogue->fresh()->calories)->toBe(717.0);
    });
});

describe('delete', function () {
    it('removes a custom food with its aliases and units', function () {
        $food = $this->service->create($this->user, labelData([
            'aliases' => ['لبنة السوبرماركت'],
            'units' => [['unit' => 'tbsp', 'label_ar' => 'ملعقة كبيرة', 'grams' => 15]],
        ]));

        $this->service->delete($food);

        expect(Food::find($food->id))->toBeNull()
            ->and(DB::table('food_aliases')->where('food_id', $food->id)->count())->toBe(0)
            ->and(DB::table('food_units')->where('food_id', $food->id)->count())->toBe(0);
    });

    it('refuses to delete a catalogue food', function () {
        $catalogue = Food::factory()->create(['user_id' => null, 'slug' => 'butter-test']);

        expect(fn () => $this->service->delete($catalogue))->toThrow(AuthorizationException::class)
            ->and(Food::find($catalogue->id))->not->toBeNull();
    });

    it('refuses to delete a food that a recipe still uses', function () {
        $food = $this->service->create($this->user, labelData());
        $recipeId = DB::table('recipes')->insertGetId(array_filter([
            'user_id' => $this->user->id, 'name' => 'فتة لبنة', 'servings' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]));
        DB::table('recipe_ingredients')->insert([
            'recipe_id' => $recipeId, 'food_id' => $food->id, 'quantity' => 100, 'unit' => 'g', 'grams' => 100,
        ]);

        expect(fn () => $this->service->delete($food))->toThrow(FoodInUseException::class)
            ->and(Food::find($food->id))->not->toBeNull();
    });
});
