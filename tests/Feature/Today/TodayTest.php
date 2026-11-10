<?php

use App\Enums\Meal;
use App\Livewire\Today\Index;
use App\Livewire\Today\NextMealSuggester;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Profile;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\FoodLogService;
use App\Services\Recipes\RecipeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $this->user->id]);
    $this->log = app(FoodLogService::class);
    // per 100 g: 100 kcal, P10, C5, fiber 2, F4 → net 3
    $this->food = Food::factory()->create(['name_ar' => 'طعام تجريبي', 'name_en' => 'Test food']);
    $this->actingAs($this->user);
    app()->setLocale('en');
});

function todaySummary(User $user, ?string $date = null)
{
    return app(DailySummaryService::class)->forDate($user, $date ? CarbonImmutable::parse($date) : CarbonImmutable::today());
}

it('renders the summary numbers produced by the daily summary service', function () {
    $this->log->logFood($this->user, $this->food, 200, 'g', Meal::Lunch, today());
    $summary = todaySummary($this->user);

    $this->get('/today')
        ->assertOk()
        ->assertSee('Test food')
        ->assertSeeInOrder(['Net carbs left', number_format(abs($summary->remaining->netCarbs), 0)])
        ->assertSee(number_format($summary->totals->calories))
        ->assertSee('Breakfast')->assertSee('Lunch')->assertSee('Dinner')->assertSee('Snack');
});

it('keeps the four meals in enum order', function () {
    $this->get('/today')->assertSeeInOrder(['Breakfast', 'Lunch', 'Dinner', 'Snack']);
});

it('shows the lower bound marker and explanation when a food has no fiber figure', function () {
    $noFiber = Food::factory()->create(['name_en' => 'Mystery', 'fiber_g' => null]);
    $this->log->logFood($this->user, $noFiber, 100, 'g', Meal::Snack, today());

    expect(todaySummary($this->user)->totals->isComplete())->toBeFalse();

    $this->get('/today')->assertOk()->assertSee('≥')->assertSee('These totals are a minimum');
});

it('has no lower bound marker when everything is known', function () {
    $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Snack, today());

    $this->get('/today')->assertDontSee('These totals are a minimum');
});

it('logs an entry when the picker dispatches food-picked and totals change', function () {
    $before = todaySummary($this->user)->totals->calories;

    Livewire::test(Index::class)
        ->set('addMeal', 'dinner')
        ->dispatch('food-picked', context: 'today', foodId: $this->food->id, quantity: 150, unit: 'g', weighedCooked: false);

    $entry = FoodLogEntry::first();
    expect($entry->meal)->toBe(Meal::Dinner)
        ->and($entry->quantity)->toBe(150.0)
        ->and(todaySummary($this->user)->totals->calories)->toBe($before + 150.0);
});

it('ignores picker events of another context', function () {
    Livewire::test(Index::class)
        ->dispatch('food-picked', context: 'recipe', foodId: $this->food->id, quantity: 150, unit: 'g', weighedCooked: false);

    expect(FoodLogEntry::count())->toBe(0);
});

it('shows a friendly error for an unknown unit instead of failing', function () {
    Livewire::test(Index::class)
        ->dispatch('food-picked', context: 'today', foodId: $this->food->id, quantity: 1, unit: 'piece', weighedCooked: false)
        ->assertDispatched('toast');

    expect(FoodLogEntry::count())->toBe(0);
});

it('cannot log another user\'s custom food', function () {
    $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);

    Livewire::test(Index::class)
        ->dispatch('food-picked', context: 'today', foodId: $foreign->id, quantity: 100, unit: 'g', weighedCooked: false);

    expect(FoodLogEntry::count())->toBe(0);
});

it('recalculates an entry when edited', function () {
    $entry = $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, today());

    Livewire::test(Index::class)
        ->call('edit', $entry->id)
        ->assertSet('editQuantity', 100)
        ->set('editQuantity', 250)
        ->set('editMeal', 'dinner')
        ->call('saveEdit')
        ->assertHasNoErrors();

    $entry->refresh();
    expect($entry->quantity)->toBe(250.0)->and($entry->calories)->toBe(250.0)->and($entry->meal)->toBe(Meal::Dinner)
        ->and(todaySummary($this->user)->totals->calories)->toBe(250.0);
});

it('rejects an invalid quantity inline', function () {
    $entry = $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, today());

    Livewire::test(Index::class)->call('edit', $entry->id)->set('editQuantity', 'abc')->call('saveEdit')->assertHasErrors('editQuantity');

    expect($entry->refresh()->quantity)->toBe(100.0);
});

it('does not allow moving an entry to a future date', function () {
    $entry = $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, today());

    Livewire::test(Index::class)->call('edit', $entry->id)->set('editDate', today()->addDay()->toDateString())->call('saveEdit')->assertHasErrors('editDate');
});

it('deletes an entry after confirmation and updates the totals', function () {
    $entry = $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, today());

    Livewire::test(Index::class)->call('confirmDelete', $entry->id)->assertSet('deletingId', $entry->id)->call('delete');

    expect(FoodLogEntry::count())->toBe(0)->and(todaySummary($this->user)->totals->calories)->toBe(0.0);
});

it('cannot edit or delete another user\'s entry', function () {
    $other = User::factory()->create();
    $entry = $this->log->logFood($other, $this->food, 100, 'g', Meal::Lunch, today());

    Livewire::test(Index::class)->call('edit', $entry->id)->assertForbidden();
    Livewire::test(Index::class)->call('confirmDelete', $entry->id)->assertForbidden();

    expect(FoodLogEntry::count())->toBe(1)->and($entry->refresh()->quantity)->toBe(100.0);
});

it('navigates between days and never goes into the future', function () {
    $yesterday = today()->subDay()->toDateString();

    Livewire::test(Index::class)
        ->call('nextDay')->assertSet('date', '')
        ->call('previousDay')->assertSet('date', $yesterday)
        ->call('nextDay')->assertSet('date', '')
        ->call('goTo', today()->addDays(3)->toDateString())->assertSet('date', '')
        ->call('goTo', 'garbage')->assertSet('date', '')
        ->call('goTo', $yesterday)->call('goToday')->assertSet('date', '');
});

it('shows entries of the requested date and logs into that day', function () {
    $day = today()->subDays(2);
    $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, $day);

    $this->get('/today?date='.$day->toDateString())->assertOk()->assertSee('Edit Test food');
    $this->get('/today')->assertDontSee('Edit Test food');
    $this->get('/today?date='.today()->addDay()->toDateString())->assertOk();

    Livewire::withQueryParams(['date' => $day->toDateString()])->test(Index::class)
        ->dispatch('food-picked', context: 'today', foodId: $this->food->id, quantity: 50, unit: 'g', weighedCooked: false);

    expect(FoodLogEntry::whereDate('logged_on', $day->toDateString())->count())->toBe(2);
});

it('offers quick add from the user\'s own history', function () {
    $entry = $this->log->logFood($this->user, $this->food, 120, 'g', Meal::Lunch, today()->subDay());

    Livewire::test(Index::class)->assertSee('Test food')->call('repeat', $entry->id);

    expect(FoodLogEntry::whereDate('logged_on', today())->count())->toBe(1);
});

it('suggests only foods that fit the remaining net carbs', function () {
    // allow 25 g net, eat 24 g net already → 1 g left
    $this->user->profile->update(['target_net_carbs_g' => 25]);
    $this->log->logFood($this->user, Food::factory()->create(['name_en' => 'Sweet', 'carbs_g' => 24, 'fiber_g' => 0, 'calories' => 100]), 100, 'g', Meal::Lunch, today());

    $fits = Food::factory()->create(['name_en' => 'Fits food', 'carbs_g' => 0.5, 'fiber_g' => 0, 'protein_g' => 20, 'fat_g' => 10, 'calories' => 180]);
    $tooMuch = Food::factory()->create(['name_en' => 'Too sugary', 'carbs_g' => 30, 'fiber_g' => 0, 'protein_g' => 1, 'fat_g' => 1, 'calories' => 130]);
    $this->log->logFood($this->user, $fits, 100, 'g', Meal::Snack, today()->subDay());
    $this->log->logFood($this->user, $tooMuch, 100, 'g', Meal::Snack, today()->subDay());

    $names = app(NextMealSuggester::class)->suggest($this->user, todaySummary($this->user))->pluck('name')->all();

    expect($names)->toContain('Fits food')->not->toContain('Too sugary');
    Livewire::test(Index::class)->assertSee('Fits food');
});

it('suggests nothing when there are no targets or calories are used up', function () {
    $this->user->profile->delete();

    Livewire::test(Index::class)->assertSee('Set up your profile to get suggestions.');
});

it('suggests the user\'s own recipe by serving', function () {
    $this->user->profile->update(['target_net_carbs_g' => 50]);
    $recipe = app(RecipeService::class)->save($this->user, [
        'name' => 'My omelette', 'servings' => 1,
        'ingredients' => [['food_id' => Food::factory()->create(['carbs_g' => 1, 'fiber_g' => 0, 'protein_g' => 15, 'fat_g' => 12, 'calories' => 170])->id, 'quantity' => 100, 'unit' => 'g']],
    ]);

    Livewire::test(Index::class)->assertSee('My omelette')->call('suggestRecipe', $recipe->id)->set('recipeServings', 2)->call('logRecipe');

    expect(FoodLogEntry::where('recipe_id', $recipe->id)->first()->quantity)->toBe(2.0);
});

it('cannot log another user\'s recipe', function () {
    $recipe = Recipe::factory()->create();

    Livewire::test(Index::class)->call('suggestRecipe', $recipe->id)->assertForbidden();
});

it('shows an empty state for a brand new user', function () {
    $this->get('/today')->assertOk()->assertSee('Your foods will appear here')->assertSee('Nothing logged yet');
});

it('links to the coach and smart log only when those routes exist', function () {
    $html = $this->get('/today')->assertOk()->getContent();

    expect(str_contains($html, 'Ask the coach'))->toBe(Route::has('coach'))
        ->and(str_contains($html, 'Log with text or photo'))->toBe(Route::has('log.smart'));
});

it('renders in both Arabic (rtl) and English (ltr)', function () {
    $this->get('/today')->assertSee('dir="ltr"', false)->assertSee('Today');

    $this->user->update(['locale' => 'ar']);
    $this->get('/today')->assertOk()->assertSee('dir="rtl"', false)->assertSee('اليوم')->assertSee('ماذا آكل بعد ذلك؟');
});

it('requires authentication and a profile', function () {
    auth()->logout();
    $this->get('/today')->assertRedirect(route('login'));
});
