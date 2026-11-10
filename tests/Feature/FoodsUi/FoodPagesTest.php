<?php

use App\Enums\Meal;
use App\Livewire\Foods\Form;
use App\Livewire\Foods\Index;
use App\Livewire\Foods\Show;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Profile;
use App\Models\User;
use App\Services\Logging\FoodLogService;
use App\Services\Recipes\RecipeService;
use Database\Seeders\FoodCatalogueSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
    app()->setLocale('en');
});

function validForm($component, array $overrides = [])
{
    $values = array_merge(['name_ar' => 'بسكويت كيتو', 'name_en' => 'Keto biscuit', 'calories' => '450', 'protein_g' => '20', 'carbs_g' => '10', 'fat_g' => '35'], $overrides);
    foreach ($values as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

describe('browse', function () {
    it('lists and searches the catalogue and my ingredients', function () {
        $this->seed(FoodCatalogueSeeder::class);
        Food::factory()->create(['user_id' => $this->user->id, 'name_en' => 'Zed own', 'name_ar' => 'خاص']);

        $this->get('/foods')->assertOk()->assertSee('My ingredients');
        Livewire::test(Index::class)->set('query', 'لبنة')->assertSee(Food::where('slug', 'labneh')->first()->displayName());
        Livewire::test(Index::class)->set('tab', 'mine')->assertSee('Zed own')->assertDontSee('Labneh');
    });

    it('shows an empty state for my ingredients', function () {
        Livewire::test(Index::class)->set('tab', 'mine')->assertSee('No ingredients of your own yet');
    });
});

describe('show', function () {
    it('shows facts with unknown values as a dash, never zero, and the estimate notice', function () {
        $food = Food::factory()->create(['name_en' => 'Unclear', 'fiber_g' => null, 'saturated_fat_g' => null, 'is_estimate' => true, 'notes_en' => 'Guessed from a similar product', 'source' => 'My guess']);

        $this->get(route('foods.show', $food))->assertOk()
            ->assertSee('Unclear')->assertSee('unknown')->assertSee('Unknown means the source does not give this value')
            ->assertSee('Estimated values')->assertSee('Guessed from a similar product')->assertSee('My guess')
            ->assertSee('Total carbs')->assertSee('Net carbs')->assertSee('Saturated fat');
    });

    it('links the variants of a food to each other', function () {
        $this->seed(FoodCatalogueSeeder::class);
        $parent = Food::where('slug', 'kebab-mince-regular')->first();
        $variant = Food::where('slug', 'kebab-mince-lean')->first();

        $this->get(route('foods.show', $parent))->assertSee(route('foods.show', $variant), false);
        $this->get(route('foods.show', $variant))->assertSee(route('foods.show', $parent), false);
    });

    it('lists household units and logs the food through the picker event', function () {
        $this->seed(FoodCatalogueSeeder::class);
        $egg = Food::where('slug', 'egg-whole-raw')->first();

        Livewire::test(Show::class, ['food' => $egg])
            ->dispatch('food-picked', context: 'food-show', foodId: $egg->id, quantity: 2, unit: 'g', weighedCooked: false);

        expect(FoodLogEntry::where('food_id', $egg->id)->count())->toBe(1);
    });

    it('hides other users\' foods and lets only the owner edit', function () {
        $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->get(route('foods.show', $foreign))->assertForbidden();

        $this->seed(FoodCatalogueSeeder::class);
        $catalogue = Food::whereNull('user_id')->first();
        $this->get(route('foods.show', $catalogue))->assertOk()->assertDontSee(route('foods.edit', $catalogue), false);

        $own = Food::factory()->create(['user_id' => $this->user->id]);
        $this->get(route('foods.show', $own))->assertOk()->assertSee(route('foods.edit', $own), false);
    });
});

describe('create / edit', function () {
    it('creates a food from label values', function () {
        validForm(Livewire::test(Form::class))->call('save')->assertHasNoErrors()->assertRedirect();

        $food = Food::where('user_id', $this->user->id)->first();
        expect($food->name_ar)->toBe('بسكويت كيتو')->and($food->calories)->toBe(450.0)->and($food->fiber_g)->toBeNull()->and($food->saturated_fat_g)->toBeNull();
    });

    it('stores net-basis carbohydrate as total carbohydrate', function () {
        validForm(Livewire::test(Form::class), ['carbs_g' => '5', 'fiber_g' => '3', 'label_basis' => 'net'])->call('save')->assertHasNoErrors();

        $food = Food::where('user_id', $this->user->id)->first();
        expect($food->carbs_g)->toBe(8.0)->and($food->fiber_g)->toBe(3.0)->and($food->per100g()->netCarbs())->toBe(5.0);
    });

    it('converts per-serving label values to per 100 g', function () {
        validForm(Livewire::test(Form::class), ['basisMode' => 'serving', 'per_grams' => '50', 'calories' => '200', 'protein_g' => '10', 'carbs_g' => '4', 'fat_g' => '15'])->call('save')->assertHasNoErrors();

        $food = Food::where('user_id', $this->user->id)->first();
        expect($food->calories)->toBe(400.0)->and($food->protein_g)->toBe(20.0)->and($food->fat_g)->toBe(30.0);
    });

    it('shows the Atwater warning live without blocking the save', function () {
        $component = validForm(Livewire::test(Form::class), ['calories' => '900', 'protein_g' => '5', 'carbs_g' => '5', 'fat_g' => '5']);
        $component->assertSee('do not match the macros');

        $component->call('save')->assertHasNoErrors();
        expect(Food::where('user_id', $this->user->id)->count())->toBe(1);
    });

    it('reports hard validation errors on the right field', function () {
        validForm(Livewire::test(Form::class), ['protein_g' => '150'])->call('save')->assertHasErrors('protein_g');
        Livewire::test(Form::class)->call('save')->assertHasErrors(['name_ar', 'calories']);
        expect(Food::where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('saves household units with a default', function () {
        $component = validForm(Livewire::test(Form::class))->call('addUnit')->set('units.0.label', 'قطعة')->set('units.0.grams', '30');
        $component->call('save')->assertHasNoErrors();

        $unit = Food::where('user_id', $this->user->id)->first()->units->first();
        expect($unit->grams)->toBe(30.0)->and($unit->is_default)->toBeTrue();
    });

    it('edits an own food', function () {
        $food = Food::factory()->create(['user_id' => $this->user->id, 'name_en' => 'Old', 'name_ar' => 'قديم', 'calories' => 100, 'protein_g' => 10, 'carbs_g' => 5, 'fiber_g' => 2, 'fat_g' => 4]);

        Livewire::test(Form::class, ['food' => $food])
            ->assertSet('name_en', 'Old')->assertSet('calories', '100')
            ->set('name_en', 'New')->set('fiber_g', '')
            ->call('save')->assertHasNoErrors();

        $food->refresh();
        expect($food->name_en)->toBe('New')->and($food->fiber_g)->toBeNull()->and($food->calories)->toBe(100.0);
    });

    it('does not let anyone edit a catalogue food or another user\'s food', function () {
        $catalogue = Food::factory()->create();
        $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->get(route('foods.edit', $catalogue))->assertForbidden();
        $this->get(route('foods.edit', $foreign))->assertForbidden();
        $this->get(route('foods.create'))->assertOk()->assertSee('New ingredient');
    });

    it('deletes an own food but keeps logged entries', function () {
        $food = Food::factory()->create(['user_id' => $this->user->id]);
        $entry = app(FoodLogService::class)->logFood($this->user, $food, 100, 'g', Meal::Lunch, today());

        Livewire::test(Form::class, ['food' => $food])->call('delete')->assertRedirect(route('foods.index'));

        expect(Food::find($food->id))->toBeNull()->and($entry->refresh()->calories)->toBe(100.0);
    });

    it('explains why a food used in a recipe cannot be deleted', function () {
        $food = Food::factory()->create(['user_id' => $this->user->id]);
        app(RecipeService::class)->save($this->user, ['name' => 'R', 'servings' => 1, 'ingredients' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']]]);

        Livewire::test(Show::class, ['food' => $food])->call('delete')->assertSet('deleteError', fn ($m) => str_contains($m, 'recipe'));

        expect(Food::find($food->id))->not->toBeNull();
    });

    it('cannot delete a catalogue food through the component', function () {
        $catalogue = Food::factory()->create();

        Livewire::test(Show::class, ['food' => $catalogue])->call('delete')->assertForbidden();
        expect(Food::find($catalogue->id))->not->toBeNull();
    });
});

describe('locales', function () {
    it('renders the food pages in English (ltr) and Arabic (rtl)', function () {
        $own = Food::factory()->create(['user_id' => $this->user->id]);

        foreach (['/foods', '/foods/create', route('foods.show', $own, false), route('foods.edit', $own, false)] as $url) {
            $this->get($url)->assertOk()->assertSee('dir="ltr"', false);
        }

        $this->user->update(['locale' => 'ar']);
        foreach (['/foods', '/foods/create', route('foods.show', $own, false), route('foods.edit', $own, false)] as $url) {
            $this->get($url)->assertOk()->assertSee('dir="rtl"', false);
        }
        $this->get('/foods')->assertSee('كل الأطعمة');
    });

    it('requires authentication', function () {
        auth()->logout();
        $this->get('/foods')->assertRedirect(route('login'));
    });
});
