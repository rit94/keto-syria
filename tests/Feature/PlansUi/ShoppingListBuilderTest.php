<?php

use App\Models\Food;
use App\Models\User;
use App\Services\Plans\MealPlanService;
use App\Services\Plans\ShoppingListBuilder;
use App\Services\Recipes\RecipeService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->egg = Food::factory()->create(['name_en' => 'Egg', 'category' => 'eggs']);
    $this->egg->units()->create(['unit' => 'piece', 'label_ar' => 'حبة', 'label_en' => 'piece', 'grams' => 50]);
    $this->beef = Food::factory()->create(['name_en' => 'Beef', 'category' => 'meat']);
    $this->oil = Food::factory()->create(['name_en' => 'Oil', 'category' => 'fats']);

    // 400 g beef + 20 g oil, 4 servings
    $this->recipe = app(RecipeService::class)->save($this->user, [
        'name' => 'Kofta', 'servings' => 4,
        'ingredients' => [
            ['food_id' => $this->beef->id, 'quantity' => 400, 'unit' => 'g'],
            ['food_id' => $this->oil->id, 'quantity' => 20, 'unit' => 'g'],
        ],
    ]);
});

function shopping(array $items, $user, $self): array
{
    $plan = app(MealPlanService::class)->save($user, [
        'name' => 'P', 'starts_on' => '2026-11-10', 'days' => 3, 'items' => $items,
    ]);

    return app(ShoppingListBuilder::class)->build($plan)
        ->mapWithKeys(fn ($g) => [$g['category'] => collect($g['items'])->mapWithKeys(fn ($l) => [$l['food']->name_en => $l['grams']])->all()])
        ->all();
}

it('sums grams per food through the unit resolver', function () {
    $list = shopping([
        ['planned_on' => '2026-11-10', 'meal' => 'breakfast', 'food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
        ['planned_on' => '2026-11-11', 'meal' => 'breakfast', 'food_id' => $this->egg->id, 'quantity' => 3, 'unit' => 'piece'],
        ['planned_on' => '2026-11-11', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 0.2, 'unit' => 'kg'],
    ], $this->user, $this);

    expect($list['eggs']['Egg'])->toBe(250.0)
        ->and($list['meat']['Beef'])->toBe(200.0);
});

it('expands recipes by servings and merges with plain foods', function () {
    $list = shopping([
        ['planned_on' => '2026-11-10', 'meal' => 'dinner', 'recipe_id' => $this->recipe->id, 'quantity' => 2, 'unit' => 'serving'],
        ['planned_on' => '2026-11-11', 'meal' => 'dinner', 'recipe_id' => $this->recipe->id, 'quantity' => 1, 'unit' => 'serving'],
        ['planned_on' => '2026-11-11', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g'],
    ], $this->user, $this);

    // 3 of 4 servings = 0.75 of the recipe
    expect($list['meat']['Beef'])->toEqualWithDelta(400 * 0.75 + 100, 0.0001)
        ->and($list['fats']['Oil'])->toEqualWithDelta(15.0, 0.0001);
});

it('expands recipes planned by grams against the finished weight', function () {
    $this->recipe->update(['cooked_weight_g' => 840]);   // 420 raw → 840 cooked

    $list = shopping([
        ['planned_on' => '2026-11-10', 'meal' => 'dinner', 'recipe_id' => $this->recipe->id, 'quantity' => 210, 'unit' => 'g'],
    ], $this->user, $this);

    expect($list['meat']['Beef'])->toEqualWithDelta(100.0, 0.0001);
});

it('orders groups by category and items by name', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'P', 'starts_on' => '2026-11-10', 'days' => 1,
        'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'lunch', 'food_id' => $this->oil->id, 'quantity' => 10, 'unit' => 'g'],
            ['planned_on' => '2026-11-10', 'meal' => 'lunch', 'food_id' => $this->egg->id, 'quantity' => 1, 'unit' => 'piece'],
            ['planned_on' => '2026-11-10', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 10, 'unit' => 'g'],
        ],
    ]);

    expect(app(ShoppingListBuilder::class)->build($plan)->pluck('category')->all())->toBe(['meat', 'eggs', 'fats']);
});

it('skips items whose recipe was deleted and handles an empty plan', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'P', 'starts_on' => '2026-11-10', 'days' => 1,
        'items' => [['planned_on' => '2026-11-10', 'meal' => 'lunch', 'recipe_id' => $this->recipe->id, 'quantity' => 1, 'unit' => 'serving']],
    ]);
    app(RecipeService::class)->delete($this->recipe);

    expect(app(ShoppingListBuilder::class)->build($plan->fresh()))->toHaveCount(0);
});
