<?php

use App\Domain\Nutrition\RecipeNutrition;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\Recipes\RecipeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->recipes = app(RecipeService::class);
    $this->user = User::factory()->create();

    $this->beef = Food::factory()->create([
        'calories' => 250, 'protein_g' => 26, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 15, 'saturated_fat_g' => 6,
    ]);
    $this->oil = Food::factory()->create([
        'calories' => 884, 'protein_g' => 0, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 100, 'saturated_fat_g' => 13.808,
    ]);
    $this->oil->units()->create(['unit' => 'tbsp', 'label_ar' => 'ملعقة كبيرة', 'label_en' => 'tbsp', 'grams' => 13.5]);
    $this->avocado = Food::factory()->create([
        'calories' => 160, 'protein_g' => 2, 'carbs_g' => 8.53, 'fiber_g' => 6.7, 'fat_g' => 14.66, 'saturated_fat_g' => 2.13,
    ]);

    // 500 g beef + 20 g oil = 520 g raw; 1426.8 kcal, P 130, F 95
    $this->data = [
        'name' => 'كفتة',
        'description' => 'وصف',
        'instructions' => 'اشوِ اللحم',
        'servings' => 4,
        'ingredients' => [
            ['food_id' => $this->beef->id, 'quantity' => 500, 'unit' => 'g'],
            ['food_id' => $this->oil->id, 'quantity' => 20, 'unit' => 'g'],
        ],
    ];
});

describe('save', function () {
    it('creates a recipe with its ingredients in order', function () {
        $recipe = $this->recipes->save($this->user, $this->data);

        expect($recipe)->toBeInstanceOf(Recipe::class)
            ->and($recipe->exists)->toBeTrue()
            ->and($recipe->user_id)->toBe($this->user->id)
            ->and($recipe->name)->toBe('كفتة')
            ->and($recipe->description)->toBe('وصف')
            ->and($recipe->instructions)->toBe('اشوِ اللحم')
            ->and($recipe->servings)->toBe(4.0)
            ->and($recipe->cooked_weight_g)->toBeNull()
            ->and($recipe->fresh()->origin)->toBe('user')
            ->and($recipe->ingredients)->toHaveCount(2)
            ->and($recipe->ingredients->pluck('food_id')->all())->toBe([$this->beef->id, $this->oil->id])
            ->and($recipe->ingredients->pluck('position')->all())->toBe([0, 1])
            ->and($recipe->ingredients[0]->grams)->toBe(500.0);
    });

    it('resolves ingredient units to grams and keeps what was entered', function () {
        $this->data['ingredients'] = [
            ['food_id' => $this->beef->id, 'quantity' => 0.5, 'unit' => 'kg'],
            ['food_id' => $this->oil->id, 'quantity' => 2, 'unit' => 'tbsp'],
            ['food_id' => $this->avocado->id, 'quantity' => 1, 'unit' => 'ouqiya'],
            ['food_id' => $this->avocado->id, 'quantity' => 30],
        ];

        $ingredients = $this->recipes->save($this->user, $this->data)->fresh()->ingredients;

        expect($ingredients->pluck('grams')->all())->toBe([500.0, 27.0, 200.0, 30.0])
            ->and($ingredients->pluck('unit')->all())->toBe(['kg', 'tbsp', 'ouqiya', 'g'])
            ->and($ingredients->pluck('quantity')->all())->toBe([0.5, 2.0, 1.0, 30.0]);
    });

    it('stores an optional cooked weight and origin', function () {
        $recipe = $this->recipes->save($this->user, [...$this->data, 'cooked_weight_g' => 400, 'origin' => 'ai'])->fresh();

        expect($recipe->cooked_weight_g)->toBe(400.0)->and($recipe->origin)->toBe('ai');
    });

    it('replaces the ingredients when updating', function () {
        $recipe = $this->recipes->save($this->user, $this->data);

        $updated = $this->recipes->save($this->user, [
            'name' => 'كفتة بالأفوكادو',
            'servings' => 2,
            'cooked_weight_g' => 300,
            'ingredients' => [['food_id' => $this->avocado->id, 'quantity' => 100, 'unit' => 'g']],
        ], $recipe);

        expect($updated->id)->toBe($recipe->id)
            ->and(Recipe::count())->toBe(1)
            ->and($updated->name)->toBe('كفتة بالأفوكادو')
            ->and($updated->servings)->toBe(2.0)
            ->and($updated->description)->toBeNull()
            ->and($updated->ingredients)->toHaveCount(1)
            ->and($updated->ingredients[0]->food_id)->toBe($this->avocado->id)
            ->and(RecipeIngredient::count())->toBe(1)
            ->and($this->recipes->nutrition($updated)->total->calories)->toEqualWithDelta(160.0, 1e-6);
    });

    it("allows the user's own custom food", function () {
        $custom = Food::factory()->create(['user_id' => $this->user->id]);
        $this->data['ingredients'][] = ['food_id' => $custom->id, 'quantity' => 10, 'unit' => 'g'];

        expect($this->recipes->save($this->user, $this->data)->ingredients)->toHaveCount(3);
    });

    it("rejects another user's custom food and writes nothing", function () {
        $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->data['ingredients'][] = ['food_id' => $foreign->id, 'quantity' => 10, 'unit' => 'g'];

        expect(fn () => $this->recipes->save($this->user, $this->data))->toThrow(AuthorizationException::class);
        expect(Recipe::count())->toBe(0)->and(RecipeIngredient::count())->toBe(0);
    });

    it('rejects a food that does not exist', function () {
        $this->data['ingredients'][] = ['food_id' => 999999, 'quantity' => 10, 'unit' => 'g'];

        expect(fn () => $this->recipes->save($this->user, $this->data))->toThrow(AuthorizationException::class);
    });

    it("refuses to overwrite another user's recipe", function () {
        $recipe = $this->recipes->save($this->user, $this->data);
        $stranger = User::factory()->create();

        expect(fn () => $this->recipes->save($stranger, [...$this->data, 'name' => 'hijacked'], $recipe))
            ->toThrow(AuthorizationException::class);
        expect($recipe->fresh()->name)->toBe('كفتة')->and($recipe->fresh()->user_id)->toBe($this->user->id);
    });

    it('reports a unit the ingredient food does not define as a validation error on that row', function () {
        $this->data['ingredients'][1]['unit'] = 'cup';

        try {
            $this->recipes->save($this->user, $this->data);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('ingredients.1.unit');
        }

        expect(Recipe::count())->toBe(0);
    });

    it('keeps the old recipe intact when an update fails', function () {
        $recipe = $this->recipes->save($this->user, $this->data);

        expect(fn () => $this->recipes->save($this->user, [
            'name' => 'broken',
            'servings' => 2,
            'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 1, 'unit' => 'slice']],
        ], $recipe))->toThrow(ValidationException::class);

        $fresh = $recipe->fresh();
        expect($fresh->name)->toBe('كفتة')->and($fresh->servings)->toBe(4.0)->and($fresh->ingredients)->toHaveCount(2);
    });

    it('validates the recipe data', function (array $override, string $field) {
        $data = [...$this->data, ...$override];

        try {
            $this->recipes->save($this->user, $data);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field);
        }

        expect(Recipe::count())->toBe(0);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'zero servings' => [['servings' => 0], 'servings'],
        'negative servings' => [['servings' => -2], 'servings'],
        'zero cooked weight' => [['cooked_weight_g' => 0], 'cooked_weight_g'],
        'bad origin' => [['origin' => 'robot'], 'origin'],
        'no ingredients' => [['ingredients' => []], 'ingredients'],
        'ingredient without food' => [['ingredients' => [['quantity' => 5, 'unit' => 'g']]], 'ingredients.0.food_id'],
        'zero quantity' => [['ingredients' => [['food_id' => 1, 'quantity' => 0, 'unit' => 'g']]], 'ingredients.0.quantity'],
        'negative quantity' => [['ingredients' => [['food_id' => 1, 'quantity' => -5, 'unit' => 'g']]], 'ingredients.0.quantity'],
        'text quantity' => [['ingredients' => [['food_id' => 1, 'quantity' => 'some', 'unit' => 'g']]], 'ingredients.0.quantity'],
    ]);
});

describe('nutrition', function () {
    it('returns totals, per serving and per 100 g from the raw weight when no cooked weight is set', function () {
        $n = $this->recipes->nutrition($this->recipes->save($this->user, $this->data));

        expect($n)->toBeInstanceOf(RecipeNutrition::class)
            ->and($n->total->calories)->toEqualWithDelta(1426.8, 1e-6)
            ->and($n->total->protein)->toEqualWithDelta(130.0, 1e-6)
            ->and($n->total->fat)->toEqualWithDelta(95.0, 1e-6)
            ->and($n->total->saturatedFat)->toEqualWithDelta(32.7616, 1e-6)
            ->and($n->total->netCarbs())->toBe(0.0)
            ->and($n->servings)->toBe(4.0)
            ->and($n->perServing->calories)->toEqualWithDelta(356.7, 1e-6)
            ->and($n->perServing->protein)->toEqualWithDelta(32.5, 1e-6)
            ->and($n->rawWeightGrams)->toBe(520.0)
            ->and($n->finishedWeightGrams)->toBe(520.0)
            ->and($n->finishedWeightIsMeasured)->toBeFalse()
            ->and($n->per100g->calories)->toEqualWithDelta(1426.8 / 5.2, 1e-6);
    });

    it('uses the measured cooked weight for per 100 g', function () {
        $n = $this->recipes->nutrition($this->recipes->save($this->user, [...$this->data, 'cooked_weight_g' => 400]));

        expect($n->finishedWeightIsMeasured)->toBeTrue()
            ->and($n->finishedWeightGrams)->toBe(400.0)
            ->and($n->per100g->calories)->toEqualWithDelta(356.7, 1e-6)
            ->and($n->per100g->protein)->toEqualWithDelta(32.5, 1e-6)
            // totals and servings are unaffected by cooking loss
            ->and($n->total->calories)->toEqualWithDelta(1426.8, 1e-6)
            ->and($n->perServing->calories)->toEqualWithDelta(356.7, 1e-6);
    });

    it('can be asked for a different number of servings without saving', function () {
        $recipe = $this->recipes->save($this->user, $this->data);

        $n = $this->recipes->nutrition($recipe, 6);

        expect($n->servings)->toBe(6.0)
            ->and($n->perServing->calories)->toEqualWithDelta(237.8, 1e-6)
            ->and($recipe->fresh()->servings)->toBe(4.0);
    });

    it('works on a recipe loaded fresh from the database', function () {
        $id = $this->recipes->save($this->user, $this->data)->id;

        expect($this->recipes->nutrition(Recipe::find($id))->total->calories)->toEqualWithDelta(1426.8, 1e-6);
    });

    it('reflects the current facts of the ingredient foods', function () {
        $recipe = $this->recipes->save($this->user, $this->data);
        $this->beef->update(['calories' => 200]);

        expect($this->recipes->nutrition(Recipe::find($recipe->id))->total->calories)->toEqualWithDelta(1176.8, 1e-6);
    });

    it('propagates unknown fiber from an ingredient', function () {
        $labneh = Food::factory()->create(['carbs_g' => 5, 'fiber_g' => null, 'saturated_fat_g' => null]);
        $this->data['ingredients'][] = ['food_id' => $labneh->id, 'quantity' => 100, 'unit' => 'g'];
        $this->data['ingredients'][] = ['food_id' => $this->avocado->id, 'quantity' => 100, 'unit' => 'g'];

        $n = $this->recipes->nutrition($this->recipes->save($this->user, $this->data));

        expect($n->total->missing)->toBe(['fiber', 'saturated_fat'])
            ->and($n->perServing->isComplete())->toBeFalse()
            ->and($n->total->fiber)->toEqualWithDelta(6.7, 1e-6)
            ->and($n->total->netCarbs())->toEqualWithDelta(5 + 8.53 - 6.7, 1e-6);
    });
});

describe('isEstimate', function () {
    it('is false when every ingredient is measured data', function () {
        expect($this->recipes->isEstimate($this->recipes->save($this->user, $this->data)))->toBeFalse();
    });

    it('is true as soon as one ingredient food is an estimate', function () {
        $estimate = Food::factory()->create(['is_estimate' => true]);
        $this->data['ingredients'][] = ['food_id' => $estimate->id, 'quantity' => 5, 'unit' => 'g'];

        expect($this->recipes->isEstimate($this->recipes->save($this->user, $this->data)))->toBeTrue();
    });
});

describe('delete', function () {
    it('removes the recipe and its ingredients but not the foods', function () {
        $recipe = $this->recipes->save($this->user, $this->data);

        $this->recipes->delete($recipe);

        expect(Recipe::count())->toBe(0)
            ->and(RecipeIngredient::count())->toBe(0)
            ->and(Food::whereKey($this->beef->id)->exists())->toBeTrue();
    });
});
