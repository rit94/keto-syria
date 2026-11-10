<?php

use App\Domain\Nutrition\Nutrients;
use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\User;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->log = app(FoodLogService::class);
    $this->summary = app(DailySummaryService::class);
    $this->user = User::factory()->create(['locale' => 'ar']);
    $this->today = Carbon::parse('2026-11-10');

    // per 100 g: 143 kcal, P 12.56, C 0.72, fiber 0, F 9.51, sat 3.126
    $this->egg = Food::factory()->create([
        'name_ar' => 'بيض', 'name_en' => 'Egg',
        'calories' => 143, 'protein_g' => 12.56, 'carbs_g' => 0.72, 'fiber_g' => 0, 'fat_g' => 9.51, 'saturated_fat_g' => 3.126,
    ]);
    $this->egg->units()->create(['unit' => 'piece', 'label_ar' => 'حبة', 'label_en' => 'piece', 'grams' => 50]);

    // raw beef, loses 30 % when cooked
    $this->beef = Food::factory()->create([
        'name_ar' => 'لحم بقر', 'name_en' => 'Beef', 'state' => 'raw', 'cooked_yield' => 0.70,
        'calories' => 250, 'protein_g' => 26, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 15, 'saturated_fat_g' => 6,
    ]);
});

describe('logFood', function () {
    it('stores an engine snapshot for a weight in grams', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 150, 'g', Meal::Breakfast, $this->today);

        $entry = $entry->fresh();

        expect($entry->user_id)->toBe($this->user->id)
            ->and($entry->food_id)->toBe($this->egg->id)
            ->and($entry->recipe_id)->toBeNull()
            ->and($entry->logged_on->toDateString())->toBe('2026-11-10')
            ->and($entry->meal)->toBe(Meal::Breakfast)
            ->and($entry->source)->toBe(LogSource::Manual)
            ->and($entry->group_id)->toBeNull()
            ->and($entry->quantity)->toBe(150.0)
            ->and($entry->unit)->toBe('g')
            ->and($entry->grams)->toBe(150.0)
            ->and($entry->weighed_cooked)->toBeFalse()
            ->and($entry->calories)->toEqualWithDelta(214.5, 1e-6)
            ->and($entry->protein_g)->toEqualWithDelta(18.84, 1e-6)
            ->and($entry->carbs_g)->toEqualWithDelta(1.08, 1e-6)
            ->and($entry->fiber_g)->toEqualWithDelta(0.0, 1e-6)
            ->and($entry->fat_g)->toEqualWithDelta(14.265, 1e-6)
            ->and($entry->saturated_fat_g)->toEqualWithDelta(4.689, 1e-6)
            ->and($entry->missing)->toBe([])
            ->and($entry->is_estimate)->toBeFalse();
    });

    it('resolves household units to grams and keeps what the user entered', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 3, 'piece', Meal::Breakfast, $this->today)->fresh();

        expect($entry->quantity)->toBe(3.0)
            ->and($entry->unit)->toBe('piece')
            ->and($entry->grams)->toBe(150.0)
            ->and($entry->calories)->toEqualWithDelta(214.5, 1e-6);
    });

    it('resolves the Syrian ouqiya', function () {
        $entry = $this->log->logFood($this->user, $this->beef, 1, 'ouqiya', Meal::Lunch, $this->today)->fresh();

        expect($entry->grams)->toBe(200.0)->and($entry->calories)->toEqualWithDelta(500.0, 1e-6);
    });

    it('snapshots the display name in the locale of the user', function () {
        $english = User::factory()->create(['locale' => 'en']);

        expect($this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Lunch, $this->today)->description)->toBe('بيض')
            ->and($this->log->logFood($english, $this->egg, 100, 'g', Meal::Lunch, $this->today)->description)->toBe('Egg');
    });

    it('keeps the snapshot when the food facts change later', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Lunch, $this->today);

        $this->egg->update(['calories' => 999, 'name_ar' => 'اسم جديد']);

        expect($entry->fresh()->calories)->toEqualWithDelta(143.0, 1e-6)
            ->and($entry->fresh()->description)->toBe('بيض');
    });

    it('records source and group id when given', function () {
        $group = (string) Str::uuid();
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Snack, $this->today, LogSource::Voice, $group)->fresh();

        expect($entry->source)->toBe(LogSource::Voice)->and($entry->group_id)->toBe($group);
    });

    it('propagates unknown fiber and saturated fat into the snapshot', function () {
        $labneh = Food::factory()->create(['calories' => 160, 'carbs_g' => 5, 'fiber_g' => null, 'saturated_fat_g' => null]);

        $entry = $this->log->logFood($this->user, $labneh, 50, 'g', Meal::Breakfast, $this->today)->fresh();

        expect($entry->fiber_g)->toBeNull()
            ->and($entry->saturated_fat_g)->toBeNull()
            ->and($entry->missing)->toBe(['fiber', 'saturated_fat'])
            ->and($entry->nutrients()->netCarbs())->toEqualWithDelta(2.5, 1e-6)
            ->and($entry->nutrients()->isComplete())->toBeFalse();
    });

    it('copies the estimate flag of the food', function () {
        $estimate = Food::factory()->create(['is_estimate' => true]);

        expect($this->log->logFood($this->user, $estimate, 100, 'g', Meal::Lunch, $this->today)->is_estimate)->toBeTrue()
            ->and($this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Lunch, $this->today)->is_estimate)->toBeFalse();
    });

    it("allows the user's own custom food", function () {
        $custom = Food::factory()->create(['user_id' => $this->user->id]);

        expect($this->log->logFood($this->user, $custom, 100, 'g', Meal::Lunch, $this->today)->exists)->toBeTrue();
    });

    it("rejects another user's custom food", function () {
        $custom = Food::factory()->create(['user_id' => User::factory()->create()->id]);

        expect(fn () => $this->log->logFood($this->user, $custom, 100, 'g', Meal::Lunch, $this->today))
            ->toThrow(AuthorizationException::class);
        expect(FoodLogEntry::count())->toBe(0);
    });

    it('rejects an unknown unit', function () {
        expect(fn () => $this->log->logFood($this->user, $this->beef, 1, 'piece', Meal::Lunch, $this->today))
            ->toThrow(UnknownUnitException::class);
        expect(FoodLogEntry::count())->toBe(0);
    });

    it('rejects a quantity that is not finite and positive', function (float $quantity) {
        expect(fn () => $this->log->logFood($this->user, $this->egg, $quantity, 'g', Meal::Lunch, $this->today))
            ->toThrow(InvalidQuantityException::class);
        expect(FoodLogEntry::count())->toBe(0);
    })->with([0.0, -50.0, NAN, INF]);
});

describe('cooked-weight logging', function () {
    it('converts a cooked weight to the raw equivalent and stores that in grams', function () {
        $entry = $this->log->logFood($this->user, $this->beef, 140, 'g', Meal::Dinner, $this->today, weighedCooked: true)->fresh();

        expect($entry->quantity)->toBe(140.0)          // what the user weighed
            ->and($entry->unit)->toBe('g')
            ->and($entry->grams)->toEqualWithDelta(200.0, 1e-6)   // raw equivalent used for the maths
            ->and($entry->weighed_cooked)->toBeTrue()
            ->and($entry->calories)->toEqualWithDelta(500.0, 1e-6)
            ->and($entry->protein_g)->toEqualWithDelta(52.0, 1e-6)
            ->and($entry->fat_g)->toEqualWithDelta(30.0, 1e-6);
    });

    it('gives the same nutrition as logging the raw weight of the same piece', function () {
        $cooked = $this->log->logFood($this->user, $this->beef, 140, 'g', Meal::Dinner, $this->today, weighedCooked: true);
        $raw = $this->log->logFood($this->user, $this->beef, 200, 'g', Meal::Dinner, $this->today);

        expect($cooked->calories)->toEqualWithDelta($raw->calories, 1e-9)
            ->and($cooked->protein_g)->toEqualWithDelta($raw->protein_g, 1e-9);
    });

    it('refuses to guess when the raw food has no cooked yield', function () {
        $chicken = Food::factory()->create(['state' => 'raw', 'cooked_yield' => null]);

        try {
            $this->log->logFood($this->user, $chicken, 140, 'g', Meal::Dinner, $this->today, weighedCooked: true);
            $this->fail('expected LoggingException');
        } catch (LoggingException $e) {
            expect($e->reason)->toBe(LoggingException::NO_COOKED_YIELD);
        }

        expect(FoodLogEntry::count())->toBe(0);
    });

    it('does not convert a food whose facts already describe the cooked state', function () {
        $grilled = Food::factory()->create(['state' => 'cooked', 'cooked_yield' => null, 'calories' => 300]);

        $entry = $this->log->logFood($this->user, $grilled, 140, 'g', Meal::Dinner, $this->today, weighedCooked: true);

        expect($entry->grams)->toBe(140.0)->and($entry->calories)->toEqualWithDelta(420.0, 1e-9);
    });

    it('previews a cooked weight without writing', function () {
        $n = $this->log->preview($this->beef, 140, 'g', true);

        expect($n)->toBeInstanceOf(Nutrients::class)
            ->and($n->calories)->toEqualWithDelta(500.0, 1e-9)
            ->and(FoodLogEntry::count())->toBe(0);
    });
});

describe('preview', function () {
    it('returns exactly what logFood would store, without writing', function () {
        $preview = $this->log->preview($this->egg, 3, 'piece');

        expect(FoodLogEntry::count())->toBe(0);

        $entry = $this->log->logFood($this->user, $this->egg, 3, 'piece', Meal::Breakfast, $this->today);

        expect($entry->nutrients()->toArray())->toEqual($preview->toArray())
            ->and($preview->calories)->toEqualWithDelta(214.5, 1e-9);
    });

    it('rejects unknown units and bad quantities', function () {
        expect(fn () => $this->log->preview($this->beef, 1, 'cup'))->toThrow(UnknownUnitException::class)
            ->and(fn () => $this->log->preview($this->beef, 0, 'g'))->toThrow(InvalidQuantityException::class);
    });
});

describe('logMany', function () {
    it('logs all items in one group', function () {
        $entries = $this->log->logMany($this->user, [
            ['food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
            ['food_id' => $this->beef->id, 'quantity' => 140, 'unit' => 'g', 'weighed_cooked' => true],
        ], Meal::Lunch, $this->today, LogSource::Text);

        expect($entries)->toHaveCount(2)
            ->and($entries->pluck('group_id')->unique())->toHaveCount(1)
            ->and(Str::isUuid($entries[0]->group_id))->toBeTrue()
            ->and($entries[0]->source)->toBe(LogSource::Text)
            ->and($entries[0]->meal)->toBe(Meal::Lunch)
            ->and($entries[0]->calories)->toEqualWithDelta(143.0, 1e-9)
            ->and($entries[1]->grams)->toEqualWithDelta(200.0, 1e-9)
            ->and($entries[1]->calories)->toEqualWithDelta(500.0, 1e-9)
            ->and(FoodLogEntry::where('user_id', $this->user->id)->count())->toBe(2);
    });

    it('uses a different group id for each call', function () {
        $items = [['food_id' => $this->egg->id, 'quantity' => 1, 'unit' => 'piece']];
        $a = $this->log->logMany($this->user, $items, Meal::Lunch, $this->today, LogSource::Manual);
        $b = $this->log->logMany($this->user, $items, Meal::Lunch, $this->today, LogSource::Manual);

        expect($a[0]->group_id)->not->toBe($b[0]->group_id);
    });

    it('rolls everything back when a later item has an unknown unit', function () {
        expect(fn () => $this->log->logMany($this->user, [
            ['food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
            ['food_id' => $this->beef->id, 'quantity' => 1, 'unit' => 'piece'],
        ], Meal::Lunch, $this->today, LogSource::Text))->toThrow(UnknownUnitException::class);

        expect(FoodLogEntry::count())->toBe(0);
    });

    it('rolls everything back when a later item has an invalid quantity', function () {
        expect(fn () => $this->log->logMany($this->user, [
            ['food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
            ['food_id' => $this->beef->id, 'quantity' => -5, 'unit' => 'g'],
        ], Meal::Lunch, $this->today, LogSource::Text))->toThrow(InvalidQuantityException::class);

        expect(FoodLogEntry::count())->toBe(0);
    });

    it('rolls everything back when a later item cannot be converted from cooked weight', function () {
        $noYield = Food::factory()->create(['state' => 'raw', 'cooked_yield' => null]);

        expect(fn () => $this->log->logMany($this->user, [
            ['food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
            ['food_id' => $noYield->id, 'quantity' => 100, 'unit' => 'g', 'weighed_cooked' => true],
        ], Meal::Lunch, $this->today, LogSource::Text))->toThrow(LoggingException::class);

        expect(FoodLogEntry::count())->toBe(0);
    });

    it("rolls everything back when an item is another user's custom food", function () {
        $foreign = Food::factory()->create(['user_id' => User::factory()->create()->id]);

        expect(fn () => $this->log->logMany($this->user, [
            ['food_id' => $this->egg->id, 'quantity' => 2, 'unit' => 'piece'],
            ['food_id' => $foreign->id, 'quantity' => 100, 'unit' => 'g'],
        ], Meal::Lunch, $this->today, LogSource::Text))->toThrow(AuthorizationException::class);

        expect(FoodLogEntry::count())->toBe(0);
    });

    it('treats a food that does not exist like an inaccessible one', function () {
        expect(fn () => $this->log->logMany($this->user, [
            ['food_id' => 999999, 'quantity' => 100, 'unit' => 'g'],
        ], Meal::Lunch, $this->today, LogSource::Text))->toThrow(AuthorizationException::class);
    });

    it('validates the shape of the items', function (array $items) {
        expect(fn () => $this->log->logMany($this->user, $items, Meal::Lunch, $this->today, LogSource::Text))
            ->toThrow(ValidationException::class);
        expect(FoodLogEntry::count())->toBe(0);
    })->with([
        'no items' => [[]],
        'missing food' => [[['quantity' => 1, 'unit' => 'g']]],
        'missing quantity' => [[['food_id' => 1, 'unit' => 'g']]],
        'missing unit' => [[['food_id' => 1, 'quantity' => 1]]],
        'non-numeric quantity' => [[['food_id' => 1, 'quantity' => 'lots', 'unit' => 'g']]],
    ]);
});

describe('update', function () {
    it('recalculates the snapshot when the quantity changes', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);

        $updated = $this->log->update($entry, ['quantity' => 250])->fresh();

        expect($updated->quantity)->toBe(250.0)
            ->and($updated->grams)->toBe(250.0)
            ->and($updated->calories)->toEqualWithDelta(357.5, 1e-6)
            ->and($updated->protein_g)->toEqualWithDelta(31.4, 1e-6)
            ->and($updated->fat_g)->toEqualWithDelta(23.775, 1e-6);
    });

    it('re-resolves grams when the unit changes', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);

        $updated = $this->log->update($entry, ['quantity' => 2, 'unit' => 'piece'])->fresh();

        expect($updated->unit)->toBe('piece')
            ->and($updated->grams)->toBe(100.0)
            ->and($updated->calories)->toEqualWithDelta(143.0, 1e-6);

        $updated = $this->log->update($updated, ['unit' => 'ouqiya'])->fresh();

        expect($updated->quantity)->toBe(2.0)->and($updated->grams)->toBe(400.0)->and($updated->calories)->toEqualWithDelta(572.0, 1e-6);
    });

    it('recalculates from the current food facts', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);
        $this->egg->update(['calories' => 150, 'is_estimate' => true]);

        $updated = $this->log->update($entry->fresh(), ['quantity' => 200])->fresh();

        expect($updated->calories)->toEqualWithDelta(300.0, 1e-6)->and($updated->is_estimate)->toBeTrue();
    });

    it('repeats the cooked-weight conversion', function () {
        $entry = $this->log->logFood($this->user, $this->beef, 140, 'g', Meal::Dinner, $this->today, weighedCooked: true);

        $updated = $this->log->update($entry->fresh(), ['quantity' => 70])->fresh();

        expect($updated->quantity)->toBe(70.0)
            ->and($updated->grams)->toEqualWithDelta(100.0, 1e-6)
            ->and($updated->weighed_cooked)->toBeTrue()
            ->and($updated->calories)->toEqualWithDelta(250.0, 1e-6);
    });

    it('moves an entry to another meal or day without touching the snapshot', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);
        $this->egg->update(['calories' => 999]);

        $updated = $this->log->update($entry->fresh(), ['meal' => Meal::Dinner, 'logged_on' => '2026-11-09'])->fresh();

        expect($updated->meal)->toBe(Meal::Dinner)
            ->and($updated->logged_on->toDateString())->toBe('2026-11-09')
            ->and($updated->calories)->toEqualWithDelta(143.0, 1e-6);
    });

    it('accepts the meal as a string and the date as a Carbon instance', function () {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);

        $updated = $this->log->update($entry, ['meal' => 'snack', 'logged_on' => Carbon::parse('2026-11-12 18:30')])->fresh();

        expect($updated->meal)->toBe(Meal::Snack)->and($updated->logged_on->toDateString())->toBe('2026-11-12');
    });

    it('refuses to change anything else', function (array $changes) {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);

        expect(fn () => $this->log->update($entry, $changes))->toThrow(ValidationException::class);
        expect($entry->fresh()->calories)->toEqualWithDelta(143.0, 1e-6)
            ->and($entry->fresh()->user_id)->toBe($this->user->id);
    })->with([
        'calories' => [['calories' => 1]],
        'owner' => [['user_id' => 999]],
        'food' => [['quantity' => 50, 'food_id' => 999]],
        'grams' => [['grams' => 1]],
    ]);

    it('rejects invalid values and leaves the entry unchanged', function (array $changes, string $exception) {
        $entry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);

        expect(fn () => $this->log->update($entry, $changes))->toThrow($exception);

        $fresh = $entry->fresh();
        expect($fresh->quantity)->toBe(100.0)->and($fresh->unit)->toBe('g')->and($fresh->calories)->toEqualWithDelta(143.0, 1e-6);
    })->with([
        'zero quantity' => [['quantity' => 0], ValidationException::class],
        'negative quantity' => [['quantity' => -3], ValidationException::class],
        'text quantity' => [['quantity' => 'many'], ValidationException::class],
        'bad meal' => [['meal' => 'brunch'], ValidationException::class],
        'bad date' => [['logged_on' => 'someday'], ValidationException::class],
        'unknown unit' => [['unit' => 'bucket'], UnknownUnitException::class],
    ]);

    describe('when the food was deleted', function () {
        beforeEach(function () {
            $this->entry = $this->log->logFood($this->user, $this->egg, 2, 'piece', Meal::Breakfast, $this->today);
            $this->egg->delete();
            $this->entry = $this->entry->fresh();
        });

        it('keeps the entry and its snapshot', function () {
            expect($this->entry->food_id)->toBeNull()
                ->and($this->entry->description)->toBe('بيض')
                ->and($this->entry->calories)->toEqualWithDelta(143.0, 1e-6);
        });

        it('scales the stored snapshot proportionally when the unit is unchanged', function () {
            $updated = $this->log->update($this->entry, ['quantity' => 3])->fresh();

            expect($updated->quantity)->toBe(3.0)
                ->and($updated->unit)->toBe('piece')
                ->and($updated->grams)->toEqualWithDelta(150.0, 1e-6)
                ->and($updated->calories)->toEqualWithDelta(214.5, 1e-6)
                ->and($updated->protein_g)->toEqualWithDelta(18.84, 1e-6)
                ->and($updated->fat_g)->toEqualWithDelta(14.265, 1e-6);
        });

        it('accepts the same unit passed again', function () {
            expect($this->log->update($this->entry, ['quantity' => 1, 'unit' => 'piece'])->fresh()->calories)->toEqualWithDelta(71.5, 1e-6);
        });

        it('refuses a unit change because there are no facts left to convert with', function () {
            try {
                $this->log->update($this->entry, ['quantity' => 100, 'unit' => 'g']);
                $this->fail('expected LoggingException');
            } catch (LoggingException $e) {
                expect($e->reason)->toBe(LoggingException::SOURCE_DELETED);
            }

            expect($this->entry->fresh()->calories)->toEqualWithDelta(143.0, 1e-6);
        });

        it('can still be moved to another meal', function () {
            expect($this->log->update($this->entry, ['meal' => Meal::Snack])->fresh()->meal)->toBe(Meal::Snack);
        });

        it('keeps missing-data flags when scaling', function () {
            $labneh = Food::factory()->create(['fiber_g' => null, 'saturated_fat_g' => null]);
            $entry = $this->log->logFood($this->user, $labneh, 100, 'g', Meal::Breakfast, $this->today);
            $labneh->delete();

            $updated = $this->log->update($entry->fresh(), ['quantity' => 50])->fresh();

            expect($updated->fiber_g)->toBeNull()->and($updated->missing)->toBe(['fiber', 'saturated_fat']);
        });
    });
});

describe('daily totals after changes', function () {
    beforeEach(function () {
        $this->eggEntry = $this->log->logFood($this->user, $this->egg, 100, 'g', Meal::Breakfast, $this->today);   // 143 kcal
        $this->beefEntry = $this->log->logFood($this->user, $this->beef, 200, 'g', Meal::Lunch, $this->today);     // 500 kcal
    });

    it('adds up the logged entries', function () {
        $totals = $this->summary->forDate($this->user, $this->today)->totals;

        expect($totals->calories)->toEqualWithDelta(643.0, 1e-6)
            ->and($totals->protein)->toEqualWithDelta(64.56, 1e-6)
            ->and($totals->fat)->toEqualWithDelta(39.51, 1e-6)
            ->and($totals->netCarbs())->toEqualWithDelta(0.72, 1e-6);
    });

    it('is correct after editing an entry', function () {
        $this->log->update($this->eggEntry, ['quantity' => 300]);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->totals->calories)->toEqualWithDelta(929.0, 1e-6)
            ->and($summary->totals->protein)->toEqualWithDelta(89.68, 1e-6)
            ->and($summary->byMeal['breakfast']['totals']->calories)->toEqualWithDelta(429.0, 1e-6)
            ->and($summary->byMeal['lunch']['totals']->calories)->toEqualWithDelta(500.0, 1e-6);
    });

    it('is correct after deleting an entry', function () {
        $this->log->delete($this->beefEntry);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect(FoodLogEntry::find($this->beefEntry->id))->toBeNull()
            ->and($summary->entries)->toHaveCount(1)
            ->and($summary->totals->calories)->toEqualWithDelta(143.0, 1e-6)
            ->and($summary->totals->protein)->toEqualWithDelta(12.56, 1e-6)
            ->and($summary->byMeal['lunch']['entries'])->toHaveCount(0)
            ->and($summary->byMeal['lunch']['totals']->calories)->toBe(0.0);
    });

    it('is correct after moving an entry to another day', function () {
        $this->log->update($this->beefEntry, ['logged_on' => '2026-11-11']);

        expect($this->summary->forDate($this->user, $this->today)->totals->calories)->toEqualWithDelta(143.0, 1e-6)
            ->and($this->summary->forDate($this->user, Carbon::parse('2026-11-11'))->totals->calories)->toEqualWithDelta(500.0, 1e-6);
    });

    it('is zero after deleting everything', function () {
        $this->log->delete($this->eggEntry);
        $this->log->delete($this->beefEntry);

        $totals = $this->summary->forDate($this->user, $this->today)->totals;

        expect($totals->calories)->toBe(0.0)->and($totals->isComplete())->toBeTrue();
    });
});
