<?php

use App\Enums\Meal;
use App\Livewire\Recipes\Index;
use App\Livewire\Recipes\Show;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Profile;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Logging\FoodLogService;
use App\Services\Recipes\RecipeService;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('en');
    $this->user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $this->user->id]);

    $this->beef = Food::factory()->create([
        'name_en' => 'Beef', 'calories' => 250, 'protein_g' => 26, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 15, 'saturated_fat_g' => 6,
    ]);

    // 400 g beef = 1000 kcal, 4 servings → 250 kcal per serving
    $this->recipe = app(RecipeService::class)->save($this->user, [
        'name' => 'Kofta', 'servings' => 4,
        'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 400, 'unit' => 'g']],
    ]);

    $this->actingAs($this->user);
});

it('lists only my recipes with per-serving figures and filters by search', function () {
    $other = User::factory()->create();
    app(RecipeService::class)->save($other, [
        'name' => 'Secret stew', 'servings' => 1,
        'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g']],
    ]);
    app(RecipeService::class)->save($this->user, [
        'name' => 'Omelette', 'servings' => 1, 'origin' => 'ai',
        'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g']],
    ]);

    Livewire::test(Index::class)
        ->assertSee('Kofta')->assertSee('Omelette')->assertDontSee('Secret stew')
        ->assertSee(__('recipes.from_coach'))
        ->set('query', 'omel')
        ->assertSee('Omelette')->assertDontSee('Kofta')
        ->set('query', 'zzz')
        ->assertSee(__('recipes.no_results_title'));
});

it('shows the empty state when there are no recipes', function () {
    Recipe::query()->delete();

    Livewire::test(Index::class)->assertSee(__('recipes.empty_title'));
});

it('shows per-serving nutrition from the engine on the detail page', function () {
    $component = Livewire::test(Show::class, ['recipe' => $this->recipe->id]);

    $expected = app(RecipeService::class)->nutrition($this->recipe);

    expect($component->viewData('base')->perServing->toArray())->toEqual($expected->perServing->toArray());
    $component->assertSee('Kofta')->assertSee('Beef')->assertSee('250');
});

it('rescales ingredients and totals with the serving adjuster', function () {
    $component = Livewire::test(Show::class, ['recipe' => $this->recipe->id])->set('servings', 8);

    $scaled = $component->viewData('nutrition');

    expect($scaled->total->calories)->toEqualWithDelta(2000.0, 0.0001)
        ->and($scaled->perServing->calories)->toEqualWithDelta(250.0, 0.0001)
        ->and($component->viewData('ingredients')[0]['grams'])->toEqualWithDelta(800.0, 0.0001)
        ->and($component->viewData('isScaled'))->toBeTrue();

    $component->call('resetServings');
    expect($component->viewData('nutrition')->total->calories)->toEqualWithDelta(1000.0, 0.0001);
});

it('logs a recipe by serving', function () {
    Livewire::test(Show::class, ['recipe' => $this->recipe->id])
        ->set('logMeal', 'dinner')
        ->set('logDate', '2026-11-10')
        ->set('logQuantity', 1.5)
        ->call('log')
        ->assertHasNoErrors()
        ->assertDispatched('close-modal');

    $entry = FoodLogEntry::sole();

    expect($entry->recipe_id)->toBe($this->recipe->id)
        ->and($entry->unit)->toBe('serving')
        ->and($entry->meal->value)->toBe('dinner')
        ->and($entry->logged_on->toDateString())->toBe('2026-11-10')
        ->and($entry->calories)->toEqualWithDelta(375.0, 0.001);
});

it('previews and logs a recipe by grams using the cooked weight', function () {
    $this->recipe->update(['cooked_weight_g' => 800]);   // 1000 kcal / 800 g

    $component = Livewire::test(Show::class, ['recipe' => $this->recipe->id])
        ->set('logUnit', 'g')->set('logQuantity', 200);

    expect($component->viewData('preview')->calories)->toEqualWithDelta(250.0, 0.001);

    $component->call('log')->assertHasNoErrors();

    expect(FoodLogEntry::sole())->grams->toEqualWithDelta(200.0, 0.001);
});

it('logs by grams against the raw weight when no cooked weight was measured', function () {
    $component = Livewire::test(Show::class, ['recipe' => $this->recipe->id])
        ->set('logUnit', 'g')->set('logQuantity', 200);

    // 1000 kcal over the 400 g of raw ingredients
    expect($component->viewData('preview')->calories)->toEqualWithDelta(500.0, 0.001);
});

it('handles a recipe without any weight gracefully', function () {
    $weightless = Recipe::factory()->create(['user_id' => $this->user->id]);   // no ingredients at all

    Livewire::test(Show::class, ['recipe' => $weightless->id])
        ->set('logUnit', 'g')->set('logQuantity', 100)
        ->assertSee(__('nutrition_errors.recipe_has_no_weight'))
        ->call('log')
        ->assertHasErrors('logUnit');

    expect(FoodLogEntry::count())->toBe(0);
});

it('rejects an invalid log quantity', function () {
    Livewire::test(Show::class, ['recipe' => $this->recipe->id])
        ->set('logQuantity', 0)->call('log')->assertHasErrors('logQuantity');
});

it('duplicates a recipe', function () {
    Livewire::test(Show::class, ['recipe' => $this->recipe->id])->call('duplicate');

    $copy = Recipe::where('id', '!=', $this->recipe->id)->with('ingredients')->sole();

    expect($copy->name)->toBe('Kofta (copy)')
        ->and($copy->user_id)->toBe($this->user->id)
        ->and($copy->ingredients)->toHaveCount(1)
        ->and($copy->ingredients[0]->grams)->toBe(400.0);
});

it('deletes a recipe and keeps logged entries', function () {
    app(FoodLogService::class)->logRecipe(
        $this->user, $this->recipe, 1, 'serving', Meal::Lunch, today(),
    );

    Livewire::test(Show::class, ['recipe' => $this->recipe->id])
        ->call('delete')
        ->assertRedirect(route('recipes.index'));

    expect(Recipe::count())->toBe(0)
        ->and(FoodLogEntry::count())->toBe(1);
});

it('forbids other users from viewing or deleting a recipe', function () {
    $other = User::factory()->create();
    Profile::factory()->create(['user_id' => $other->id]);

    $this->actingAs($other);
    $this->get(route('recipes.show', $this->recipe))->assertForbidden();
    Livewire::test(Show::class, ['recipe' => $this->recipe->id])->assertForbidden();

    expect(Recipe::count())->toBe(1);
});

it('renders index and show in Arabic (rtl) and English (ltr)', function () {
    $this->get(route('recipes.index'))->assertOk()->assertSee('dir="ltr"', false)->assertSee('Kofta');
    $this->get(route('recipes.show', $this->recipe))->assertOk()->assertSee('dir="ltr"', false);

    $this->user->forceFill(['locale' => 'ar'])->save();

    $this->get(route('recipes.index'))->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('recipes.title', locale: 'ar'));
    $this->get(route('recipes.show', $this->recipe))->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('recipes.log.action', locale: 'ar'));
});
