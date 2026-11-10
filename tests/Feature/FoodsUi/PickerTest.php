<?php

use App\Livewire\Foods\Picker;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\FoodCatalogueSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
    app()->setLocale('en');
    $this->seed(FoodCatalogueSeeder::class);
});

it('finds foods by Syrian dialect names', function () {
    Livewire::test(Picker::class, ['context' => 'x'])
        ->set('query', 'لبنة')
        ->assertSee(Food::where('slug', 'labneh')->first()->displayName());

    Livewire::test(Picker::class, ['context' => 'x'])
        ->set('query', 'لحمة كباب')
        ->assertSee(Food::where('slug', 'kebab-mince-regular')->first()->displayName());
});

it('shows common foods on an empty query and filters by category', function () {
    $egg = Food::where('slug', 'egg-whole-raw')->first();

    Livewire::test(Picker::class, ['context' => 'x'])->assertSee($egg->displayName())
        ->set('category', 'dairy')->assertDontSee($egg->displayName());
});

it('shows per 100 g facts, state and estimate badges in results', function () {
    $estimate = Food::factory()->create(['name_en' => 'Guess cheese', 'search_text' => 'guess cheese', 'is_estimate' => true, 'confidence' => 'low', 'state' => 'raw']);

    Livewire::test(Picker::class, ['context' => 'x'])->set('query', 'Guess cheese')
        ->assertSee('Guess cheese')->assertSee('Estimate')->assertSee('Approximate')->assertSee('Raw')->assertSee('Per 100 g');
});

it('does not list other users\' custom foods', function () {
    Food::factory()->create(['user_id' => User::factory()->create()->id, 'name_en' => 'Secret stew', 'name_ar' => 'يخنة سرية', 'search_text' => 'secret stew يخنه سريه']);

    Livewire::test(Picker::class, ['context' => 'x'])->set('query', 'Secret')->assertDontSee('Secret stew');
});

it('goes to the quantity step with the default unit and dispatches food-picked on confirm', function () {
    $egg = Food::where('slug', 'egg-whole-raw')->with('units')->first();
    $default = $egg->units->firstWhere('is_default', true)?->unit ?? 'g';

    Livewire::test(Picker::class, ['context' => 'recipe-1', 'meal' => 'lunch'])
        ->call('select', $egg->id)
        ->assertSet('foodId', $egg->id)
        ->assertSet('unit', $default)
        ->set('quantity', 2)
        ->call('confirm')
        ->assertDispatched('food-picked', fn ($name, $params) => $params['context'] === 'recipe-1' && $params['foodId'] === $egg->id
            && (float) $params['quantity'] === 2.0 && $params['unit'] === $default && $params['weighedCooked'] === false && $params['meal'] === 'lunch')
        ->assertSet('foodId', null);
});

it('dispatches immediately without a quantity step when withQuantity is false', function () {
    $egg = Food::where('slug', 'egg-whole-raw')->first();

    Livewire::test(Picker::class, ['context' => 'c', 'withQuantity' => false])
        ->call('select', $egg->id)
        ->assertDispatched('food-picked', context: 'c', foodId: $egg->id, quantity: null, unit: null, weighedCooked: false);
});

it('shows the weighed-after-cooking toggle only for a raw food with a cooked yield', function () {
    $raw = Food::where('slug', 'kebab-mince-regular')->first();
    expect($raw->cooked_yield)->not->toBeNull();
    $noYield = Food::factory()->create(['state' => 'raw', 'cooked_yield' => null]);
    $cooked = Food::where('slug', 'egg-boiled')->first();

    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $raw->id)->assertSee('I weighed it after cooking');
    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $noYield->id)->assertDontSee('I weighed it after cooking');
    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $cooked->id)->assertDontSee('I weighed it after cooking');
});

it('passes the cooked flag through and ignores it for foods it does not apply to', function () {
    $raw = Food::where('slug', 'kebab-mince-regular')->first();
    $cooked = Food::where('slug', 'egg-boiled')->first();

    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $raw->id)->set('weighedCooked', true)->call('confirm')
        ->assertDispatched('food-picked', foodId: $raw->id, weighedCooked: true);

    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $cooked->id)->set('weighedCooked', true)->call('confirm')
        ->assertDispatched('food-picked', foodId: $cooked->id, weighedCooked: false);
});

it('previews nutrition from the service and shows translated errors', function () {
    $food = Food::factory()->create(['name_en' => 'Plain']);

    $component = Livewire::test(Picker::class, ['context' => 'x'])->call('select', $food->id)->set('unit', 'g')->set('quantity', 200);
    $component->assertSee('200');   // 200 kcal for 200 g

    $component->set('quantity', 0)->assertSee('The quantity must be a number greater than zero.')->call('confirm')->assertNotDispatched('food-picked');
});

it('refuses a unit the food does not define', function () {
    $food = Food::factory()->create();

    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $food->id)->set('unit', 'piece')->assertSee('is not defined for this item')->call('confirm')->assertNotDispatched('food-picked');
});

it('refuses another user\'s food', function () {
    $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);

    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $foreign->id)->assertSet('foodId', null)->assertNotDispatched('food-picked');
});

it('offers to create a custom ingredient when nothing matches', function () {
    Livewire::test(Picker::class, ['context' => 'x'])->set('query', 'qqzzxxvv')->assertSee('Create my own ingredient');
});

it('can be prefilled and reset through events', function () {
    $food = Food::factory()->create();

    Livewire::test(Picker::class, ['context' => 'x'])
        ->dispatch('food-picker-select', context: 'other', foodId: $food->id)->assertSet('foodId', null)
        ->dispatch('food-picker-select', context: 'x', foodId: $food->id, quantity: 150, unit: 'g')->assertSet('foodId', $food->id)->assertSet('quantity', 150)
        ->dispatch('food-picker-reset', context: 'x')->assertSet('foodId', null);
});

it('does not write anything itself', function () {
    $food = Food::factory()->create();

    Livewire::test(Picker::class, ['context' => 'x'])->call('select', $food->id)->call('confirm');

    expect(FoodLogEntry::count())->toBe(0);
});
