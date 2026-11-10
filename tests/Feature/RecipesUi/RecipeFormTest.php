<?php

use App\Livewire\Recipes\Form;
use App\Models\Food;
use App\Models\Profile;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $this->user->id]);

    $this->egg = Food::factory()->create([
        'name_ar' => 'بيض', 'name_en' => 'Egg',
        'calories' => 143, 'protein_g' => 12.56, 'carbs_g' => 0.72, 'fiber_g' => 0, 'fat_g' => 9.51, 'saturated_fat_g' => 3.126,
    ]);
    $this->egg->units()->create(['unit' => 'piece', 'label_ar' => 'حبة', 'label_en' => 'piece', 'grams' => 50, 'is_default' => true]);

    $this->beef = Food::factory()->create([
        'name_ar' => 'لحم بقر', 'name_en' => 'Beef',
        'calories' => 250, 'protein_g' => 26, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 15, 'saturated_fat_g' => 6,
        'state' => 'raw', 'cooked_yield' => 0.7,
    ]);

    app()->setLocale('en');
    $this->actingAs($this->user);
});

function pickFood($component, Food $food, $quantity = null, ?string $unit = null, bool $weighedCooked = false, string $context = 'recipe')
{
    return $component->dispatch('food-picked',
        context: $context, foodId: $food->id, quantity: $quantity, unit: $unit, weighedCooked: $weighedCooked, meal: null,
    );
}

it('adds ingredients from the food-picked event and ignores other contexts', function () {
    $component = Livewire::test(Form::class);

    pickFood($component, $this->egg, 2, 'piece');
    pickFood($component, $this->beef, 400, 'g')->assertDispatched('close-modal');
    pickFood($component, $this->beef, 100, 'g', context: 'today');

    $ingredients = $component->get('ingredients');

    expect($ingredients)->toHaveCount(2)
        ->and($ingredients[0])->toMatchArray(['food_id' => $this->egg->id, 'quantity' => 2.0, 'unit' => 'piece'])
        ->and($ingredients[1])->toMatchArray(['food_id' => $this->beef->id, 'quantity' => 400.0, 'unit' => 'g']);

    $component->assertSee('Egg')->assertSee('Beef');
});

it('falls back to the default unit when the picker sends no amount', function () {
    $component = Livewire::test(Form::class);

    pickFood($component, $this->egg);
    pickFood($component, $this->beef);

    expect($component->get('ingredients.0'))->toMatchArray(['quantity' => 1.0, 'unit' => 'piece'])
        ->and($component->get('ingredients.1'))->toMatchArray(['quantity' => 100.0, 'unit' => 'g']);
});

it('converts a cooked weight of a raw food to raw grams through the engine', function () {
    $component = Livewire::test(Form::class);

    pickFood($component, $this->beef, 140, 'g', weighedCooked: true);

    expect($component->get('ingredients.0.unit'))->toBe('g')
        ->and($component->get('ingredients.0.quantity'))->toEqualWithDelta(200.0, 0.0001);
});

it('ignores a food the user cannot see', function () {
    $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);

    $component = Livewire::test(Form::class);
    pickFood($component, $foreign, 100, 'g');

    expect($component->get('ingredients'))->toBe([]);
});

it('shows live nutrition equal to RecipeService output', function () {
    $component = Livewire::test(Form::class)->set('servings', 4)->set('cookedWeight', '350');

    pickFood($component, $this->egg, 2, 'piece');
    pickFood($component, $this->beef, 400, 'g');

    $expected = app(RecipeService::class)->nutrition(
        app(RecipeService::class)->save($this->user, [
            'name' => 'Reference', 'servings' => 4, 'cooked_weight_g' => 350,
            'ingredients' => [
                ['food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
                ['food_id' => $this->beef->id, 'quantity' => 400, 'unit' => 'g'],
            ],
        ])
    );

    $live = $component->viewData('nutrition');

    expect($live->total->toArray())->toEqual($expected->total->toArray())
        ->and($live->perServing->toArray())->toEqual($expected->perServing->toArray())
        ->and($live->per100g->toArray())->toEqual($expected->per100g->toArray())
        ->and($live->finishedWeightIsMeasured)->toBeTrue();

    // 100 g egg = 143 kcal, 400 g beef = 1000 kcal → 1143 kcal, 285.75 per serving
    $component->assertSee('1,143')->assertSee('286');

    // changing a quantity recomputes the panel
    $component->set('ingredients.1.quantity', '200');
    expect($component->viewData('nutrition')->total->calories)->toEqualWithDelta(643.0, 0.0001);
});

it('leaves an invalid row out of the panel and flags it', function () {
    $component = Livewire::test(Form::class);
    pickFood($component, $this->egg, 2, 'piece');
    pickFood($component, $this->beef, 400, 'g');

    $component->set('ingredients.1.quantity', '');

    expect($component->viewData('nutrition')->total->calories)->toEqualWithDelta(143.0, 0.0001)
        ->and($component->viewData('skipped'))->toBe(1);
    $component->assertSee(__('recipes.row_not_counted'));
});

it('marks incomplete nutrition as a lower bound with an explanation', function () {
    $noFiber = Food::factory()->create(['name_en' => 'Mystery', 'fiber_g' => null]);

    $component = Livewire::test(Form::class);
    pickFood($component, $noFiber, 100, 'g');

    $component->assertSee(__('recipes.nutrition.incomplete_title'))->assertSee('≥');

    $complete = Livewire::test(Form::class);
    pickFood($complete, $this->egg, 1, 'piece');
    $complete->assertDontSee(__('recipes.nutrition.incomplete_title'));
});

it('creates a recipe through the UI', function () {
    $component = Livewire::test(Form::class)
        ->set('name', 'Kofta')
        ->set('description', 'Grilled')
        ->set('instructions', "Mix\nGrill")
        ->set('servings', '4')
        ->set('cookedWeight', '300');

    pickFood($component, $this->beef, 400, 'g');
    pickFood($component, $this->egg, 1, 'piece');

    $component->call('save')->assertHasNoErrors();

    $recipe = Recipe::with('ingredients')->sole();
    $component->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->user_id)->toBe($this->user->id)
        ->and($recipe->name)->toBe('Kofta')
        ->and($recipe->servings)->toBe(4.0)
        ->and($recipe->cooked_weight_g)->toBe(300.0)
        ->and($recipe->origin)->toBe('user')
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->ingredients[0]->food_id)->toBe($this->beef->id)
        ->and($recipe->ingredients[1]->grams)->toBe(50.0);
});

it('validates with localized messages', function () {
    Livewire::test(Form::class)
        ->set('servings', '0')
        ->call('save')
        ->assertHasErrors(['name', 'servings', 'ingredients'])
        ->assertSee(__('recipes.errors.no_ingredients'));

    expect(Recipe::count())->toBe(0);
});

it('maps a unit error from the service back onto the right row', function () {
    $component = Livewire::test(Form::class)->set('name', 'Kofta');

    pickFood($component, $this->egg, 1, 'piece');
    pickFood($component, $this->beef, 2, 'g');

    $component->set('ingredients.1.unit', 'piece')   // beef has no "piece"
        ->call('save')
        ->assertHasErrors(['ingredients.1.unit'])
        ->assertHasNoErrors(['ingredients.0.unit']);

    expect(Recipe::count())->toBe(0);
});

it('reorders and removes ingredients', function () {
    $component = Livewire::test(Form::class);
    pickFood($component, $this->egg, 1, 'piece');
    pickFood($component, $this->beef, 100, 'g');

    $keys = array_column($component->get('ingredients'), 'key');

    $component->call('moveIngredient', $keys[1], -1);
    expect(array_column($component->get('ingredients'), 'food_id'))->toBe([$this->beef->id, $this->egg->id]);

    $component->call('moveIngredient', $keys[1], -1);   // already first: no-op
    expect(array_column($component->get('ingredients'), 'food_id'))->toBe([$this->beef->id, $this->egg->id]);

    $component->call('removeIngredient', $keys[1]);
    expect(array_column($component->get('ingredients'), 'food_id'))->toBe([$this->egg->id]);
});

it('edits a recipe through the UI', function () {
    $recipe = app(RecipeService::class)->save($this->user, [
        'name' => 'Kofta', 'servings' => 2, 'origin' => 'ai',
        'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 400, 'unit' => 'g']],
    ]);

    $component = Livewire::test(Form::class, ['recipe' => $recipe->id])
        ->assertSet('name', 'Kofta')
        ->assertSet('ingredients.0.food_id', $this->beef->id)
        ->set('name', 'Kofta 2')
        ->set('servings', 4)
        ->set('ingredients.0.quantity', '500');

    pickFood($component, $this->egg, 2, 'piece');

    $component->call('save')->assertHasNoErrors()->assertRedirect(route('recipes.show', $recipe));

    $recipe->refresh()->load('ingredients');

    expect(Recipe::count())->toBe(1)
        ->and($recipe->name)->toBe('Kofta 2')
        ->and($recipe->servings)->toBe(4.0)
        ->and($recipe->origin)->toBe('ai')
        ->and($recipe->ingredients->pluck('grams')->all())->toBe([500.0, 100.0]);
});

it('forbids opening or saving another user\'s recipe', function () {
    $other = User::factory()->create();
    $recipe = app(RecipeService::class)->save($other, [
        'name' => 'Private', 'servings' => 1,
        'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g']],
    ]);

    Livewire::test(Form::class, ['recipe' => $recipe->id])->assertForbidden();
    $this->get(route('recipes.edit', $recipe))->assertForbidden();
    $this->get(route('recipes.edit', 999999))->assertNotFound();
});

it('renders the create page in Arabic (rtl) and English (ltr)', function () {
    $this->get(route('recipes.create'))
        ->assertOk()
        ->assertSee('dir="ltr"', false)
        ->assertSee(__('recipes.create_title', locale: 'en'))
        ->assertSee(__('recipes.fields.cooked_weight_hint', locale: 'en'));

    $this->user->forceFill(['locale' => 'ar'])->save();

    $this->get(route('recipes.create'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('recipes.create_title', locale: 'ar'))
        ->assertSee(__('recipes.fields.cooked_weight_hint', locale: 'ar'));
});

it('requires authentication and onboarding', function () {
    auth()->logout();
    $this->get(route('recipes.create'))->assertRedirect(route('login'));
});
