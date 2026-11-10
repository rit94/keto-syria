<?php

use App\Domain\Nutrition\MacroTargets;
use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\RemainingMacros;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Profile;
use App\Models\User;
use App\Services\Logging\DailySummary;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\FoodLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->log = app(FoodLogService::class);
    $this->summary = app(DailySummaryService::class);
    $this->user = User::factory()->create();
    $this->today = Carbon::parse('2026-11-10');

    // factory food per 100 g: 100 kcal, P 10, C 5, fiber 2, F 4, sat 1
    $this->food = Food::factory()->create();
});

describe('forDate', function () {
    it('returns an empty, zeroed summary for a day with nothing logged', function () {
        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary)->toBeInstanceOf(DailySummary::class)
            ->and($summary->date)->toBeInstanceOf(CarbonImmutable::class)
            ->and($summary->date->toDateString())->toBe('2026-11-10')
            ->and($summary->entries)->toHaveCount(0)
            ->and($summary->totals->toArray())->toEqual(Nutrients::zero()->toArray())
            ->and(array_keys($summary->byMeal))->toBe(['breakfast', 'lunch', 'dinner', 'snack']);

        foreach ($summary->byMeal as $meal) {
            expect($meal['entries'])->toHaveCount(0)->and($meal['totals']->calories)->toBe(0.0);
        }
    });

    it('keys byMeal by every meal in enum order, including empty meals', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Snack, $this->today);
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Breakfast, $this->today);

        $byMeal = $this->summary->forDate($this->user, $this->today)->byMeal;

        expect(array_keys($byMeal))->toBe(Meal::values())
            ->and($byMeal['breakfast']['entries'])->toHaveCount(1)
            ->and($byMeal['lunch']['entries'])->toHaveCount(0)
            ->and($byMeal['dinner']['entries'])->toHaveCount(0)
            ->and($byMeal['snack']['entries'])->toHaveCount(1);
    });

    it('sums totals per meal and for the day from the entry snapshots', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Breakfast, $this->today);
        $this->log->logFood($this->user, $this->food, 50, 'g', Meal::Breakfast, $this->today);
        $this->log->logFood($this->user, $this->food, 250, 'g', Meal::Dinner, $this->today);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->entries)->toHaveCount(3)
            ->and($summary->byMeal['breakfast']['totals']->calories)->toEqualWithDelta(150.0, 1e-6)
            ->and($summary->byMeal['breakfast']['totals']->protein)->toEqualWithDelta(15.0, 1e-6)
            ->and($summary->byMeal['dinner']['totals']->calories)->toEqualWithDelta(250.0, 1e-6)
            ->and($summary->totals->calories)->toEqualWithDelta(400.0, 1e-6)
            ->and($summary->totals->protein)->toEqualWithDelta(40.0, 1e-6)
            ->and($summary->totals->carbs)->toEqualWithDelta(20.0, 1e-6)
            ->and($summary->totals->fiber)->toEqualWithDelta(8.0, 1e-6)
            ->and($summary->totals->netCarbs())->toEqualWithDelta(12.0, 1e-6)
            ->and($summary->totals->fat)->toEqualWithDelta(16.0, 1e-6)
            ->and($summary->totals->saturatedFat)->toEqualWithDelta(4.0, 1e-6);
    });

    it('sums snapshots, not current food facts', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, $this->today);
        $this->food->update(['calories' => 900]);

        expect($this->summary->forDate($this->user, $this->today)->totals->calories)->toEqualWithDelta(100.0, 1e-6);
    });

    it('only includes the requested day', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, Carbon::parse('2026-11-09'));
        $this->log->logFood($this->user, $this->food, 200, 'g', Meal::Lunch, $this->today);
        $this->log->logFood($this->user, $this->food, 300, 'g', Meal::Lunch, Carbon::parse('2026-11-11'));

        $summary = $this->summary->forDate($this->user, Carbon::parse('2026-11-10 23:59:59'));

        expect($summary->entries)->toHaveCount(1)->and($summary->totals->calories)->toEqualWithDelta(200.0, 1e-6);
    });

    it("never includes another user's entries", function () {
        $other = User::factory()->create();
        $this->log->logFood($other, $this->food, 500, 'g', Meal::Lunch, $this->today);
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, $this->today);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->entries)->toHaveCount(1)
            ->and($summary->entries->first()->user_id)->toBe($this->user->id)
            ->and($summary->totals->calories)->toEqualWithDelta(100.0, 1e-6);
    });

    it('has no targets or remaining when the user has no profile', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, $this->today);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->targets)->toBeNull()->and($summary->remaining)->toBeNull();
    });

    it('computes remaining against the profile targets', function () {
        // targets: 1900 kcal, P 130, F 140, C 35, net 25
        Profile::factory()->create(['user_id' => $this->user->id]);
        $this->log->logFood($this->user, $this->food, 300, 'g', Meal::Lunch, $this->today);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->targets)->toBeInstanceOf(MacroTargets::class)
            ->and($summary->targets->calories)->toBe(1900.0)
            ->and($summary->remaining)->toBeInstanceOf(RemainingMacros::class)
            ->and($summary->remaining->calories)->toEqualWithDelta(1600.0, 1e-6)
            ->and($summary->remaining->protein)->toEqualWithDelta(100.0, 1e-6)
            ->and($summary->remaining->fat)->toEqualWithDelta(128.0, 1e-6)
            ->and($summary->remaining->carbs)->toEqualWithDelta(20.0, 1e-6)
            ->and($summary->remaining->netCarbs)->toEqualWithDelta(16.0, 1e-6)
            ->and($summary->remaining->isLowerBound)->toBeFalse();
    });

    it('reports negative remaining when over target', function () {
        Profile::factory()->create(['user_id' => $this->user->id]);
        $this->log->logFood($this->user, $this->food, 2500, 'g', Meal::Lunch, $this->today);   // 2500 kcal, 75 g net carbs

        $remaining = $this->summary->forDate($this->user, $this->today)->remaining;

        expect($remaining->calories)->toEqualWithDelta(-600.0, 1e-6)
            ->and($remaining->netCarbs)->toEqualWithDelta(-50.0, 1e-6)
            ->and($remaining->protein)->toEqualWithDelta(-120.0, 1e-6);
    });

    it('flags totals and remaining as a lower bound when an entry lacks data', function () {
        Profile::factory()->create(['user_id' => $this->user->id]);
        $unknown = Food::factory()->create(['fiber_g' => null]);
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, $this->today);
        $this->log->logFood($this->user, $unknown, 100, 'g', Meal::Lunch, $this->today);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->totals->missing)->toBe(['fiber'])
            ->and($summary->totals->fiber)->toEqualWithDelta(2.0, 1e-6)
            // 10 g carbs, only 2 g of fiber known
            ->and($summary->totals->netCarbs())->toEqualWithDelta(8.0, 1e-6)
            ->and($summary->remaining->isLowerBound)->toBeTrue()
            ->and($summary->byMeal['lunch']['totals']->isComplete())->toBeFalse()
            ->and($summary->byMeal['dinner']['totals']->isComplete())->toBeTrue();
    });

    it('includes entries whose food was deleted', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, $this->today);
        $this->food->delete();

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->entries)->toHaveCount(1)->and($summary->totals->calories)->toEqualWithDelta(100.0, 1e-6);
    });

    it('stays precise over many small entries', function () {
        foreach (range(1, 60) as $i) {
            $this->log->logFood($this->user, $this->food, 0.1, 'g', Meal::Snack, $this->today);
        }

        $totals = $this->summary->forDate($this->user, $this->today)->totals;

        expect($totals->calories)->toEqualWithDelta(6.0, 1e-6)->and($totals->protein)->toEqualWithDelta(0.6, 1e-6);
    });

    it('works with factory-made snapshot rows', function () {
        FoodLogEntry::factory()->count(3)->create(['user_id' => $this->user->id, 'logged_on' => '2026-11-10', 'meal' => 'dinner']);

        $summary = $this->summary->forDate($this->user, $this->today);

        expect($summary->byMeal['dinner']['entries'])->toHaveCount(3)->and($summary->totals->calories)->toEqualWithDelta(300.0, 1e-6);
    });
});

describe('range', function () {
    it('returns one total per day, including days with no entries, in order', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, Carbon::parse('2026-11-08'));
        $this->log->logFood($this->user, $this->food, 150, 'g', Meal::Dinner, Carbon::parse('2026-11-08'));
        $this->log->logFood($this->user, $this->food, 300, 'g', Meal::Lunch, Carbon::parse('2026-11-10'));

        $range = $this->summary->range($this->user, Carbon::parse('2026-11-07'), Carbon::parse('2026-11-10'));

        expect($range->keys()->all())->toBe(['2026-11-07', '2026-11-08', '2026-11-09', '2026-11-10'])
            ->and($range['2026-11-07'])->toBeInstanceOf(Nutrients::class)
            ->and($range['2026-11-07']->calories)->toBe(0.0)
            ->and($range['2026-11-08']->calories)->toEqualWithDelta(250.0, 1e-6)
            ->and($range['2026-11-09']->calories)->toBe(0.0)
            ->and($range['2026-11-09']->isComplete())->toBeTrue()
            ->and($range['2026-11-10']->calories)->toEqualWithDelta(300.0, 1e-6);
    });

    it('excludes days outside the range and other users', function () {
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, Carbon::parse('2026-11-06'));
        $this->log->logFood($this->user, $this->food, 100, 'g', Meal::Lunch, Carbon::parse('2026-11-11'));
        $this->log->logFood(User::factory()->create(), $this->food, 100, 'g', Meal::Lunch, Carbon::parse('2026-11-08'));

        $range = $this->summary->range($this->user, Carbon::parse('2026-11-07'), Carbon::parse('2026-11-10'));

        expect($range)->toHaveCount(4)->and($range->sum(fn (Nutrients $n) => $n->calories))->toBe(0.0);
    });

    it('handles a single-day range and matches forDate', function () {
        $this->log->logFood($this->user, $this->food, 123.4, 'g', Meal::Lunch, $this->today);

        $range = $this->summary->range($this->user, $this->today, $this->today);

        expect($range)->toHaveCount(1)
            ->and($range['2026-11-10']->toArray())->toEqual($this->summary->forDate($this->user, $this->today)->totals->toArray());
    });

    it('is empty when the range is reversed', function () {
        expect($this->summary->range($this->user, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-07')))->toHaveCount(0);
    });
});
