<?php

use App\Livewire\Plans\Form;
use App\Livewire\Plans\Index;
use App\Livewire\Plans\Show;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\Plans\MealPlanService;
use App\Services\Recipes\RecipeService;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('en');
    $this->user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $this->user->id]);   // net-carb target 25 g

    $this->egg = Food::factory()->create([
        'name_en' => 'Egg', 'calories' => 143, 'protein_g' => 12.56, 'carbs_g' => 0.72, 'fiber_g' => 0, 'fat_g' => 9.51, 'saturated_fat_g' => 3.1,
    ]);
    $this->egg->units()->create(['unit' => 'piece', 'label_ar' => 'حبة', 'label_en' => 'piece', 'grams' => 50]);
    $this->bread = Food::factory()->create([
        'name_en' => 'Bread', 'calories' => 250, 'protein_g' => 8, 'carbs_g' => 50, 'fiber_g' => 2, 'fat_g' => 3, 'saturated_fat_g' => 1,
    ]);
    $this->beef = Food::factory()->create([
        'name_en' => 'Beef', 'calories' => 250, 'protein_g' => 26, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 15, 'saturated_fat_g' => 6,
    ]);

    $this->recipe = app(RecipeService::class)->save($this->user, [
        'name' => 'Kofta', 'servings' => 4,
        'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 400, 'unit' => 'g']],
    ]);

    $this->actingAs($this->user);
});

function pick($component, Food $food, $quantity, string $unit, string $meal = 'breakfast')
{
    return $component->dispatch('food-picked', context: 'plan', foodId: $food->id, quantity: $quantity, unit: $unit, weighedCooked: false, meal: $meal);
}

it('creates a plan with foods and recipes through the UI', function () {
    $component = Livewire::test(Form::class)
        ->set('name', 'Week')
        ->set('startsOn', '2026-11-10')
        ->set('days', 3);

    pick($component, $this->egg, 2, 'piece', 'breakfast');
    $component->call('openRecipePicker', 'dinner')->call('addRecipe', $this->recipe->id);
    $component->call('selectDay', 1);
    pick($component, $this->beef, 200, 'g', 'lunch');

    $component->call('save')->assertHasNoErrors();

    $plan = MealPlan::with('items')->sole();
    $component->assertRedirect(route('plans.show', $plan));

    expect($plan->days)->toBe(3)
        ->and($plan->starts_on->toDateString())->toBe('2026-11-10')
        ->and($plan->items)->toHaveCount(3);

    $byMeal = $plan->items->keyBy(fn ($i) => $i->meal->value);
    expect($byMeal['breakfast']->planned_on->toDateString())->toBe('2026-11-10')
        ->and($byMeal['breakfast']->grams)->toBe(100.0)
        ->and($byMeal['dinner']->recipe_id)->toBe($this->recipe->id)
        ->and($byMeal['dinner']->unit)->toBe('serving')
        ->and($byMeal['lunch']->planned_on->toDateString())->toBe('2026-11-11');
});

it('ignores picks from other contexts and foods the user cannot see', function () {
    $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);

    $component = Livewire::test(Form::class);
    $component->dispatch('food-picked', context: 'recipe', foodId: $this->egg->id, quantity: 1, unit: 'piece', weighedCooked: false, meal: null);
    pick($component, $foreign, 100, 'g');

    expect($component->get('items'))->toBe([]);
});

it('shows day totals equal to MealPlanService::dayNutrition and flags days over the net-carb target', function () {
    $component = Livewire::test(Form::class)->set('startsOn', '2026-11-10')->set('days', 2);

    pick($component, $this->egg, 2, 'piece');
    $component->call('openRecipePicker', 'dinner')->call('addRecipe', $this->recipe->id);
    $component->call('selectDay', 1);
    pick($component, $this->bread, 100, 'g', 'lunch');   // 48 g net carbs > 25 g target

    $component->set('name', 'Week')->call('save');
    $plan = MealPlan::sole();

    $reloaded = Livewire::test(Form::class, ['plan' => $plan->id]);
    $service = app(MealPlanService::class);

    foreach ([0, 1] as $offset) {
        $expected = $service->dayNutrition($plan, $plan->starts_on->copy()->addDays($offset));
        $live = $reloaded->viewData('dayList')[$offset];

        expect($live['totals']->toArray())->toEqual($expected->toArray());
    }

    expect($reloaded->viewData('dayList')[0]['over'])->toBeFalse()
        ->and($reloaded->viewData('dayList')[1]['over'])->toBeTrue();
    $reloaded->assertSee(__('plans.over_carbs'));
});

it('edits a plan, keeping item ids so logged items keep their link', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'Week', 'starts_on' => '2026-11-10', 'days' => 3,
        'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'breakfast', 'food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
            ['planned_on' => '2026-11-11', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g'],
        ],
    ]);
    $eaten = $plan->items->firstWhere('food_id', $this->egg->id);
    app(MealPlanService::class)->logItem($eaten);
    $entryId = $eaten->fresh()->food_log_entry_id;

    Livewire::test(Form::class, ['plan' => $plan->id])
        ->set('name', 'Renamed')
        ->set('items.0.quantity', '3')
        ->set('startsOn', '2026-11-12')        // moves the whole plan
        ->call('removeItem', Livewire::test(Form::class, ['plan' => $plan->id])->get('items.1.key'))
        ->call('save')->assertHasNoErrors();

    $plan->refresh()->load('items');
    $kept = MealPlanItem::find($eaten->id);

    expect($plan->name)->toBe('Renamed')
        ->and($plan->starts_on->toDateString())->toBe('2026-11-12')
        ->and($kept->quantity)->toBe(3.0)
        ->and($kept->planned_on->toDateString())->toBe('2026-11-12')
        ->and($kept->food_log_entry_id)->toBe($entryId);
});

it('drops items beyond the shortened plan and validates 1–14 days', function () {
    $component = Livewire::test(Form::class)->set('days', 3);
    $component->call('selectDay', 2);
    pick($component, $this->egg, 1, 'piece');

    $component->set('days', 2);
    expect($component->get('items'))->toBe([]);

    $component->set('days', 20)->call('save')->assertHasErrors('days');
    $component->set('days', 0)->call('save')->assertHasErrors('days');
});

it('copies a day to another day', function () {
    $component = Livewire::test(Form::class)->set('startsOn', '2026-11-10')->set('days', 3);

    pick($component, $this->egg, 2, 'piece');
    pick($component, $this->beef, 100, 'g', 'lunch');
    $component->set('copyTo', 2)->call('copyDay');

    $items = collect($component->get('items'));

    expect($items->where('day', 2))->toHaveCount(2)
        ->and($items->where('day', 0))->toHaveCount(2)
        ->and($items->where('day', 2)->pluck('meal')->sort()->values()->all())->toBe(['breakfast', 'lunch']);
});

it('maps item errors back onto the row', function () {
    $component = Livewire::test(Form::class)->set('days', 1);
    pick($component, $this->beef, 100, 'g');

    $component->set('items.0.unit', 'piece')->call('save')->assertHasErrors(['items.0.unit']);
    expect(MealPlan::count())->toBe(0);
});

it('lists plans with the current plan first', function () {
    $service = app(MealPlanService::class);
    $service->save($this->user, ['name' => 'Old plan', 'starts_on' => today()->subDays(30)->toDateString(), 'days' => 7]);
    $service->save($this->user, ['name' => 'Later plan', 'starts_on' => today()->addDays(10)->toDateString(), 'days' => 7]);
    $service->save($this->user, ['name' => 'Now plan', 'starts_on' => today()->subDays(1)->toDateString(), 'days' => 7]);
    $service->save(User::factory()->create(), ['name' => 'Foreign plan', 'starts_on' => today()->toDateString(), 'days' => 7]);

    Livewire::test(Index::class)
        ->assertSeeInOrder(['Now plan', 'Later plan', 'Old plan'])
        ->assertDontSee('Foreign plan');
});

it('shows the plan with today preselected and logs an item only once', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'Week', 'starts_on' => today()->subDay()->toDateString(), 'days' => 3,
        'items' => [
            ['planned_on' => today()->toDateString(), 'meal' => 'breakfast', 'food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
            ['planned_on' => today()->toDateString(), 'meal' => 'breakfast', 'food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g'],
        ],
    ]);
    $item = $plan->items->first();

    $component = Livewire::test(Show::class, ['plan' => $plan->id])->assertSet('activeDay', 1);

    $component->call('logItem', $item->id)->call('logItem', $item->id);

    expect(FoodLogEntry::count())->toBe(1)
        ->and($item->fresh()->food_log_entry_id)->not->toBeNull()
        ->and(FoodLogEntry::sole()->source->value)->toBe('plan');

    $component->assertSee(__('plans.show.logged_badge'));

    // whole meal: only the remaining item is logged
    $component->call('logMeal', 'breakfast');
    expect(FoodLogEntry::count())->toBe(2);

    $component->call('logMeal', 'breakfast');
    expect(FoodLogEntry::count())->toBe(2);
});

it('preselects the first day when today is outside the plan', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'Future', 'starts_on' => today()->addDays(5)->toDateString(), 'days' => 3,
    ]);

    Livewire::test(Show::class, ['plan' => $plan->id])->assertSet('activeDay', 0);
});

it('shows the aggregated shopping list on its tab', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'Week', 'starts_on' => '2026-11-10', 'days' => 2,
        'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'dinner', 'recipe_id' => $this->recipe->id, 'quantity' => 2, 'unit' => 'serving'],
            ['planned_on' => '2026-11-11', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g'],
        ],
    ]);

    $component = Livewire::test(Show::class, ['plan' => $plan->id])->set('tab', 'shopping');

    // 2/4 of 400 g + 100 g = 300 g
    $component->assertSee('Beef')->assertSee('300');
});

it('deletes a plan and keeps logged entries', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'Week', 'starts_on' => '2026-11-10', 'days' => 1,
        'items' => [['planned_on' => '2026-11-10', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g']],
    ]);
    app(MealPlanService::class)->logItem($plan->items->first());

    Livewire::test(Show::class, ['plan' => $plan->id])->call('delete')->assertRedirect(route('plans.index'));

    expect(MealPlan::count())->toBe(0)->and(FoodLogEntry::count())->toBe(1);
});

it('forbids other users from viewing, editing, logging or deleting a plan', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'Mine', 'starts_on' => '2026-11-10', 'days' => 1,
        'items' => [['planned_on' => '2026-11-10', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g']],
    ]);

    $other = User::factory()->create();
    Profile::factory()->create(['user_id' => $other->id]);
    $this->actingAs($other);

    $this->get(route('plans.show', $plan))->assertForbidden();
    $this->get(route('plans.edit', $plan))->assertForbidden();
    Livewire::test(Show::class, ['plan' => $plan->id])->assertForbidden();
    Livewire::test(Form::class, ['plan' => $plan->id])->assertForbidden();
    $this->get(route('plans.show', 999999))->assertNotFound();

    expect(MealPlan::count())->toBe(1)->and(FoodLogEntry::count())->toBe(0);
});

it('renders every plan page in Arabic (rtl) and English (ltr)', function () {
    $plan = app(MealPlanService::class)->save($this->user, [
        'name' => 'Week', 'starts_on' => today()->toDateString(), 'days' => 2,
        'items' => [['planned_on' => today()->toDateString(), 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100, 'unit' => 'g']],
    ]);
    $urls = [route('plans.index'), route('plans.create'), route('plans.show', $plan), route('plans.edit', $plan)];

    foreach ($urls as $url) {
        $this->get($url)->assertOk()->assertSee('dir="ltr"', false);
    }

    $this->user->forceFill(['locale' => 'ar'])->save();

    foreach ($urls as $url) {
        $this->get($url)->assertOk()->assertSee('dir="rtl"', false);
    }
});
