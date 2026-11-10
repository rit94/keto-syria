<?php

use App\Domain\Nutrition\Nutrients;
use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\User;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\LoggingException;
use App\Services\Plans\MealPlanService;
use App\Services\Recipes\RecipeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->plans = app(MealPlanService::class);
    $this->user = User::factory()->create();

    // per 100 g: 143 kcal, P 12.56
    $this->egg = Food::factory()->create([
        'name_ar' => 'بيض', 'name_en' => 'Egg',
        'calories' => 143, 'protein_g' => 12.56, 'carbs_g' => 0.72, 'fiber_g' => 0, 'fat_g' => 9.51, 'saturated_fat_g' => 3.126,
    ]);
    $this->egg->units()->create(['unit' => 'piece', 'label_ar' => 'حبة', 'label_en' => 'piece', 'grams' => 50]);
    $this->beef = Food::factory()->create([
        'calories' => 250, 'protein_g' => 26, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 15, 'saturated_fat_g' => 6,
    ]);

    // 400 g beef = 1000 kcal, 4 servings of 250 kcal, 400 g raw
    $this->recipe = app(RecipeService::class)->save($this->user, [
        'name' => 'Kofta', 'servings' => 4,
        'ingredients' => [['food_id' => $this->beef->id, 'quantity' => 400, 'unit' => 'g']],
    ]);

    $this->data = [
        'name' => 'أسبوع الكيتو',
        'starts_on' => '2026-11-10',
        'days' => 7,
        'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'breakfast', 'food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece', 'note' => 'مسلوق'],
            ['planned_on' => '2026-11-10', 'meal' => 'dinner', 'recipe_id' => $this->recipe->id, 'quantity' => 1.5, 'unit' => 'serving'],
            ['planned_on' => '2026-11-11', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 200, 'unit' => 'g'],
        ],
    ];
});

describe('save', function () {
    it('creates a plan with its items', function () {
        $plan = $this->plans->save($this->user, $this->data);

        expect($plan)->toBeInstanceOf(MealPlan::class)
            ->and($plan->user_id)->toBe($this->user->id)
            ->and($plan->name)->toBe('أسبوع الكيتو')
            ->and($plan->starts_on->toDateString())->toBe('2026-11-10')
            ->and($plan->days)->toBe(7)
            ->and($plan->fresh()->origin)->toBe('user')
            ->and($plan->items)->toHaveCount(3);

        [$egg, $kofta, $beef] = $plan->items->sortBy('id')->values()->all();

        expect($egg->food_id)->toBe($this->egg->id)
            ->and($egg->recipe_id)->toBeNull()
            ->and($egg->meal)->toBe(Meal::Breakfast)
            ->and($egg->planned_on->toDateString())->toBe('2026-11-10')
            ->and($egg->quantity)->toBe(2.0)
            ->and($egg->unit)->toBe('piece')
            ->and($egg->grams)->toBe(100.0)
            ->and($egg->note)->toBe('مسلوق')
            ->and($egg->food_log_entry_id)->toBeNull()
            ->and($kofta->recipe_id)->toBe($this->recipe->id)
            ->and($kofta->food_id)->toBeNull()
            ->and($kofta->unit)->toBe('serving')
            ->and($kofta->grams)->toBeNull()
            ->and($beef->grams)->toBe(200.0);
    });

    it('defaults the unit to g for foods and serving for recipes', function () {
        $plan = $this->plans->save($this->user, [...$this->data, 'items' => [
            ['planned_on' => '2026-11-10', 'meal' => Meal::Lunch, 'food_id' => $this->beef->id, 'quantity' => 150],
            ['planned_on' => '2026-11-10', 'meal' => Meal::Dinner, 'recipe_id' => $this->recipe->id, 'quantity' => 1],
        ]]);

        expect($plan->items->sortBy('id')->pluck('unit')->all())->toBe(['g', 'serving'])
            ->and($plan->items->sortBy('id')->first()->grams)->toBe(150.0);
    });

    it('stores the grams of a recipe planned by weight', function () {
        $plan = $this->plans->save($this->user, [...$this->data, 'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'dinner', 'recipe_id' => $this->recipe->id, 'quantity' => 120, 'unit' => 'g'],
        ]]);

        expect($plan->items[0]->grams)->toBe(120.0);
    });

    it('accepts a plan without items and an ai origin', function () {
        $plan = $this->plans->save($this->user, ['name' => 'Empty', 'starts_on' => '2026-11-10', 'days' => 3, 'origin' => 'ai']);

        expect($plan->items)->toHaveCount(0)->and($plan->fresh()->origin)->toBe('ai');
    });

    it('accepts the first and the last day of the range', function () {
        $plan = $this->plans->save($this->user, [...$this->data, 'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100],
            ['planned_on' => '2026-11-16', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100],
        ]]);

        expect($plan->items)->toHaveCount(2);
    });

    it('rejects items planned outside the plan range', function (string $date) {
        $this->data['items'][2]['planned_on'] = $date;

        try {
            $this->plans->save($this->user, $this->data);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('items.2.planned_on');
        }

        expect(MealPlan::count())->toBe(0)->and(MealPlanItem::count())->toBe(0);
    })->with(['day before' => '2026-11-09', 'day after the last' => '2026-11-17', 'far away' => '2027-01-01']);

    it('requires exactly one of food_id / recipe_id', function (array $reference) {
        $this->data['items'] = [['planned_on' => '2026-11-10', 'meal' => 'lunch', 'quantity' => 1, ...$reference]];

        try {
            $this->plans->save($this->user, $this->data);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('items.0.food_id');
        }

        expect(MealPlan::count())->toBe(0);
    })->with([
        'neither' => [[]],
        'both null' => [['food_id' => null, 'recipe_id' => null]],
        'both set' => [['food_id' => 1, 'recipe_id' => 1]],
    ]);

    it('reports a unit the food does not define on that item', function () {
        $this->data['items'][2]['unit'] = 'piece';

        try {
            $this->plans->save($this->user, $this->data);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('items.2.unit');
        }

        expect(MealPlan::count())->toBe(0);
    });

    it('accepts only serving or g for recipe items', function () {
        $this->data['items'][1]['unit'] = 'kg';

        try {
            $this->plans->save($this->user, $this->data);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('items.1.unit');
        }
    });

    it('validates plan fields', function (array $override, string $field) {
        try {
            $this->plans->save($this->user, [...$this->data, ...$override]);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field);
        }

        expect(MealPlan::count())->toBe(0);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'bad start date' => [['starts_on' => 'soon'], 'starts_on'],
        'zero days' => [['days' => 0], 'days'],
        'fractional days' => [['days' => 2.5], 'days'],
        'bad origin' => [['origin' => 'robot'], 'origin'],
        'bad meal' => [['items' => [['planned_on' => '2026-11-10', 'meal' => 'brunch', 'food_id' => 1, 'quantity' => 1]]], 'items.0.meal'],
        'zero quantity' => [['items' => [['planned_on' => '2026-11-10', 'meal' => 'lunch', 'food_id' => 1, 'quantity' => 0]]], 'items.0.quantity'],
        'missing date' => [['items' => [['meal' => 'lunch', 'food_id' => 1, 'quantity' => 1]]], 'items.0.planned_on'],
    ]);

    it("rejects another user's custom food", function () {
        $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->data['items'][] = ['planned_on' => '2026-11-12', 'meal' => 'lunch', 'food_id' => $foreign->id, 'quantity' => 100];

        expect(fn () => $this->plans->save($this->user, $this->data))->toThrow(AuthorizationException::class);
        expect(MealPlan::count())->toBe(0);
    });

    it("allows the user's own custom food", function () {
        $custom = Food::factory()->create(['user_id' => $this->user->id]);
        $this->data['items'][] = ['planned_on' => '2026-11-12', 'meal' => 'lunch', 'food_id' => $custom->id, 'quantity' => 100];

        expect($this->plans->save($this->user, $this->data)->items)->toHaveCount(4);
    });

    it("rejects another user's recipe", function () {
        $stranger = User::factory()->create();
        $this->data['items'] = [['planned_on' => '2026-11-10', 'meal' => 'lunch', 'recipe_id' => $this->recipe->id, 'quantity' => 1]];

        expect(fn () => $this->plans->save($stranger, $this->data))->toThrow(AuthorizationException::class);
        expect(MealPlan::count())->toBe(0);
    });

    it("refuses to overwrite another user's plan", function () {
        $plan = $this->plans->save($this->user, $this->data);

        expect(fn () => $this->plans->save(User::factory()->create(), ['name' => 'x', 'starts_on' => '2026-11-10', 'days' => 1], $plan))
            ->toThrow(AuthorizationException::class);
        expect($plan->fresh()->name)->toBe('أسبوع الكيتو')->and($plan->fresh()->items)->toHaveCount(3);
    });

    it('replaces the items when updating', function () {
        $plan = $this->plans->save($this->user, $this->data);

        $updated = $this->plans->save($this->user, [
            'name' => 'Short plan', 'starts_on' => '2026-11-20', 'days' => 2,
            'items' => [['planned_on' => '2026-11-21', 'meal' => 'snack', 'food_id' => $this->egg->id, 'quantity' => 1, 'unit' => 'piece']],
        ], $plan);

        expect($updated->id)->toBe($plan->id)
            ->and(MealPlan::count())->toBe(1)
            ->and($updated->name)->toBe('Short plan')
            ->and($updated->days)->toBe(2)
            ->and($updated->items)->toHaveCount(1)
            ->and(MealPlanItem::count())->toBe(1)
            ->and($updated->items[0]->meal)->toBe(Meal::Snack);
    });

    it('updates an item in place when its id is sent back, keeping the eaten link', function () {
        $plan = $this->plans->save($this->user, $this->data);
        $eggItem = $plan->items->firstWhere('food_id', $this->egg->id);
        $entry = $this->plans->logItem($eggItem);

        $updated = $this->plans->save($this->user, [...$this->data, 'items' => [
            ['id' => $eggItem->id, 'planned_on' => '2026-11-10', 'meal' => 'breakfast', 'food_id' => $this->egg->id, 'quantity' => 3, 'unit' => 'piece'],
            ['planned_on' => '2026-11-12', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100],
        ]], $plan);

        $kept = $updated->items->firstWhere('id', $eggItem->id);

        expect($updated->items)->toHaveCount(2)
            ->and($kept->quantity)->toBe(3.0)
            ->and($kept->grams)->toBe(150.0)
            ->and($kept->food_log_entry_id)->toBe($entry->id);
    });

    it('rejects an item id that belongs to another plan', function () {
        $other = $this->plans->save($this->user, $this->data);
        $plan = $this->plans->save($this->user, $this->data);

        $this->data['items'][0]['id'] = $other->items->first()->id;

        try {
            $this->plans->save($this->user, $this->data, $plan);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('items.0.id');
        }

        expect($other->fresh()->items)->toHaveCount(3)->and($plan->fresh()->items)->toHaveCount(3);
    });

    it('keeps the old plan intact when an update fails', function () {
        $plan = $this->plans->save($this->user, $this->data);

        expect(fn () => $this->plans->save($this->user, [...$this->data, 'name' => 'broken', 'items' => [
            ['planned_on' => '2026-12-25', 'meal' => 'lunch', 'food_id' => $this->beef->id, 'quantity' => 100],
        ]], $plan))->toThrow(ValidationException::class);

        expect($plan->fresh()->name)->toBe('أسبوع الكيتو')->and($plan->fresh()->items)->toHaveCount(3);
    });
});

describe('dayNutrition', function () {
    beforeEach(function () {
        $this->plan = $this->plans->save($this->user, $this->data);
    });

    it('sums food and recipe items of the day through the engine', function () {
        $n = $this->plans->dayNutrition($this->plan, Carbon::parse('2026-11-10'));

        // 100 g egg (143 kcal, P 12.56) + 1.5 servings kofta (375 kcal, P 39)
        expect($n)->toBeInstanceOf(Nutrients::class)
            ->and($n->calories)->toEqualWithDelta(518.0, 1e-6)
            ->and($n->protein)->toEqualWithDelta(51.56, 1e-6)
            ->and($n->fat)->toEqualWithDelta(9.51 + 22.5, 1e-6)
            ->and($n->netCarbs())->toEqualWithDelta(0.72, 1e-6);
    });

    it('only counts the requested day', function () {
        expect($this->plans->dayNutrition($this->plan, Carbon::parse('2026-11-11'))->calories)->toEqualWithDelta(500.0, 1e-6);
    });

    it('is zero for a day with nothing planned', function () {
        $n = $this->plans->dayNutrition($this->plan, Carbon::parse('2026-11-13'));

        expect($n->calories)->toBe(0.0)->and($n->isComplete())->toBeTrue();
    });

    it('handles a recipe planned by grams', function () {
        $plan = $this->plans->save($this->user, [...$this->data, 'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'dinner', 'recipe_id' => $this->recipe->id, 'quantity' => 200, 'unit' => 'g'],
        ]]);

        // 200 g of 400 g raw = half the recipe
        expect($this->plans->dayNutrition($plan, Carbon::parse('2026-11-10'))->calories)->toEqualWithDelta(500.0, 1e-6);
    });

    it('uses current food facts', function () {
        $this->beef->update(['calories' => 300]);

        expect($this->plans->dayNutrition($this->plan, Carbon::parse('2026-11-11'))->calories)->toEqualWithDelta(600.0, 1e-6);
    });

    it('skips items whose food was deleted', function () {
        $this->egg->delete();

        expect($this->plans->dayNutrition($this->plan, Carbon::parse('2026-11-10'))->calories)->toEqualWithDelta(375.0, 1e-6);
    });

    it('flags missing data', function () {
        $labneh = Food::factory()->create(['fiber_g' => null]);
        $plan = $this->plans->save($this->user, [...$this->data, 'items' => [
            ['planned_on' => '2026-11-10', 'meal' => 'breakfast', 'food_id' => $labneh->id, 'quantity' => 100],
            ['planned_on' => '2026-11-10', 'meal' => 'breakfast', 'food_id' => $this->egg->id, 'quantity' => 100],
        ]]);

        expect($this->plans->dayNutrition($plan, Carbon::parse('2026-11-10'))->missing)->toBe(['fiber']);
    });
});

describe('logItem', function () {
    beforeEach(function () {
        $this->plan = $this->plans->save($this->user, $this->data);
        $this->eggItem = $this->plan->items->firstWhere('food_id', $this->egg->id);
        $this->koftaItem = $this->plan->items->firstWhere('recipe_id', $this->recipe->id);
    });

    it('writes a diary entry for a food item and links it', function () {
        $entry = $this->plans->logItem($this->eggItem);

        expect($entry)->toBeInstanceOf(FoodLogEntry::class)
            ->and($entry->user_id)->toBe($this->user->id)
            ->and($entry->food_id)->toBe($this->egg->id)
            ->and($entry->source)->toBe(LogSource::Plan)
            ->and($entry->meal)->toBe(Meal::Breakfast)
            ->and($entry->logged_on->toDateString())->toBe('2026-11-10')
            ->and($entry->quantity)->toBe(2.0)
            ->and($entry->unit)->toBe('piece')
            ->and($entry->grams)->toBe(100.0)
            ->and($entry->calories)->toEqualWithDelta(143.0, 1e-6)
            ->and($this->eggItem->fresh()->food_log_entry_id)->toBe($entry->id)
            ->and($this->eggItem->fresh()->logEntry->is($entry))->toBeTrue();
    });

    it('writes a diary entry for a recipe item', function () {
        $entry = $this->plans->logItem($this->koftaItem)->fresh();

        expect($entry->recipe_id)->toBe($this->recipe->id)
            ->and($entry->unit)->toBe('serving')
            ->and($entry->calories)->toEqualWithDelta(375.0, 1e-6)
            ->and($entry->source)->toBe(LogSource::Plan);
    });

    it('works on an item loaded fresh from the database', function () {
        expect($this->plans->logItem(MealPlanItem::find($this->eggItem->id))->calories)->toEqualWithDelta(143.0, 1e-6);
    });

    it('is idempotent: logging twice returns the same entry', function () {
        $first = $this->plans->logItem($this->eggItem);
        $second = $this->plans->logItem($this->eggItem->fresh());
        $third = $this->plans->logItem($this->eggItem);

        expect($second->id)->toBe($first->id)->and($third->id)->toBe($first->id)->and(FoodLogEntry::count())->toBe(1);
    });

    it('can be logged again after the diary entry was deleted', function () {
        $first = $this->plans->logItem($this->eggItem);
        $first->delete();

        expect($this->eggItem->fresh()->food_log_entry_id)->toBeNull();

        $second = $this->plans->logItem($this->eggItem->fresh());

        expect($second->id)->not->toBe($first->id)->and(FoodLogEntry::count())->toBe(1);
    });

    it('shows up in the daily summary', function () {
        $this->plans->logItem($this->eggItem);
        $this->plans->logItem($this->koftaItem);

        $totals = app(DailySummaryService::class)->forDate($this->user, Carbon::parse('2026-11-10'))->totals;

        expect($totals->calories)->toEqualWithDelta(518.0, 1e-6)
            ->and($totals->toArray()['calories'])
            ->toEqualWithDelta($this->plans->dayNutrition($this->plan, Carbon::parse('2026-11-10'))->calories, 1e-6);
    });

    it('refuses an item whose food was deleted', function () {
        $this->egg->delete();

        try {
            $this->plans->logItem($this->eggItem->fresh());
            $this->fail('expected LoggingException');
        } catch (LoggingException $e) {
            expect($e->reason)->toBe(LoggingException::PLAN_ITEM_EMPTY);
        }

        expect(FoodLogEntry::count())->toBe(0);
    });
});

describe('delete', function () {
    it('removes the plan and its items but keeps diary entries already logged', function () {
        $plan = $this->plans->save($this->user, $this->data);
        $entry = $this->plans->logItem($plan->items->firstWhere('food_id', $this->egg->id));

        $this->plans->delete($plan);

        expect(MealPlan::count())->toBe(0)
            ->and(MealPlanItem::count())->toBe(0)
            ->and(FoodLogEntry::whereKey($entry->id)->exists())->toBeTrue();
    });
});
