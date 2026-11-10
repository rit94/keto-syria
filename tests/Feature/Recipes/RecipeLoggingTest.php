<?php

use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;
use App\Services\Recipes\RecipeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->recipes = app(RecipeService::class);
    $this->log = app(FoodLogService::class);
    $this->user = User::factory()->create();
    $this->today = Carbon::parse('2026-11-10');

    $this->beef = Food::factory()->create([
        'calories' => 250, 'protein_g' => 26, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 15, 'saturated_fat_g' => 6,
    ]);
    $this->oil = Food::factory()->create([
        'calories' => 884, 'protein_g' => 0, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 100, 'saturated_fat_g' => 13.808,
    ]);

    // total 1426.8 kcal, P 130, F 95; 4 servings of 356.7 kcal; 520 g raw
    $this->data = [
        'name' => 'Kofta',
        'servings' => 4,
        'ingredients' => [
            ['food_id' => $this->beef->id, 'quantity' => 500, 'unit' => 'g'],
            ['food_id' => $this->oil->id, 'quantity' => 20, 'unit' => 'g'],
        ],
    ];
    $this->recipe = $this->recipes->save($this->user, $this->data);
});

describe('by serving', function () {
    it('logs quantity × per-serving nutrition with no gram weight', function () {
        $entry = $this->log->logRecipe($this->user, $this->recipe, 1.5, 'serving', Meal::Dinner, $this->today)->fresh();

        expect($entry->recipe_id)->toBe($this->recipe->id)
            ->and($entry->food_id)->toBeNull()
            ->and($entry->description)->toBe('Kofta')
            ->and($entry->quantity)->toBe(1.5)
            ->and($entry->unit)->toBe('serving')
            ->and($entry->grams)->toBeNull()
            ->and($entry->meal)->toBe(Meal::Dinner)
            ->and($entry->source)->toBe(LogSource::Manual)
            ->and($entry->calories)->toEqualWithDelta(535.05, 1e-6)
            ->and($entry->protein_g)->toEqualWithDelta(48.75, 1e-6)
            ->and($entry->fat_g)->toEqualWithDelta(35.625, 1e-6)
            ->and($entry->is_estimate)->toBeFalse();
    });

    it('logging every serving equals the whole recipe', function () {
        $entry = $this->log->logRecipe($this->user, $this->recipe, 4, 'serving', Meal::Dinner, $this->today);

        expect($entry->calories)->toEqualWithDelta(1426.8, 1e-9)->and($entry->protein_g)->toEqualWithDelta(130.0, 1e-9);
    });

    it('matches previewRecipe and writes nothing when previewing', function () {
        $preview = $this->log->previewRecipe($this->recipe, 2, 'serving');

        expect(FoodLogEntry::count())->toBe(0)->and($preview->calories)->toEqualWithDelta(713.4, 1e-9);
    });
});

describe('by grams', function () {
    it('uses the measured cooked weight', function () {
        $recipe = $this->recipes->save($this->user, [...$this->data, 'cooked_weight_g' => 400], $this->recipe);

        $entry = $this->log->logRecipe($this->user, $recipe, 150, 'g', Meal::Lunch, $this->today)->fresh();

        // per 100 g cooked = 356.7 kcal
        expect($entry->unit)->toBe('g')
            ->and($entry->quantity)->toBe(150.0)
            ->and($entry->grams)->toBe(150.0)
            ->and($entry->calories)->toEqualWithDelta(535.05, 1e-6)
            ->and($entry->protein_g)->toEqualWithDelta(48.75, 1e-6);
    });

    it('falls back to the raw ingredient weight when no cooked weight was measured', function () {
        $entry = $this->log->logRecipe($this->user, $this->recipe, 130, 'g', Meal::Lunch, $this->today)->fresh();

        // 130 g of 520 g raw = a quarter of the recipe
        expect($entry->grams)->toBe(130.0)
            ->and($entry->calories)->toEqualWithDelta(356.7, 1e-6)
            ->and($entry->protein_g)->toEqualWithDelta(32.5, 1e-6);
    });

    it('eating the whole cooked weight equals the whole recipe', function () {
        $recipe = $this->recipes->save($this->user, [...$this->data, 'cooked_weight_g' => 400], $this->recipe);

        expect($this->log->logRecipe($this->user, $recipe, 400, 'g', Meal::Lunch, $this->today)->calories)->toEqualWithDelta(1426.8, 1e-9);
    });

    it('refuses when the recipe has no weight at all', function () {
        $empty = Recipe::factory()->create(['user_id' => $this->user->id]);

        try {
            $this->log->logRecipe($this->user, $empty, 100, 'g', Meal::Lunch, $this->today);
            $this->fail('expected LoggingException');
        } catch (LoggingException $e) {
            expect($e->reason)->toBe(LoggingException::RECIPE_HAS_NO_WEIGHT);
        }

        expect(FoodLogEntry::count())->toBe(0);
    });
});

describe('guards', function () {
    it('accepts only serving or g', function (string $unit) {
        expect(fn () => $this->log->logRecipe($this->user, $this->recipe, 1, $unit, Meal::Lunch, $this->today))
            ->toThrow(UnknownUnitException::class);
        expect(FoodLogEntry::count())->toBe(0);
    })->with(['kg', 'piece', 'ouqiya', 'portion']);

    it('rejects a bad quantity', function (float $quantity) {
        expect(fn () => $this->log->logRecipe($this->user, $this->recipe, $quantity, 'serving', Meal::Lunch, $this->today))
            ->toThrow(InvalidQuantityException::class);
    })->with([0.0, -1.0, NAN, INF]);

    it("rejects another user's recipe", function () {
        $stranger = User::factory()->create();

        expect(fn () => $this->log->logRecipe($stranger, $this->recipe, 1, 'serving', Meal::Lunch, $this->today))
            ->toThrow(AuthorizationException::class);
        expect(FoodLogEntry::count())->toBe(0);
    });
});

describe('estimate flag', function () {
    it('is set when any ingredient food is an estimate', function () {
        $estimate = Food::factory()->create(['is_estimate' => true]);
        $this->data['ingredients'][] = ['food_id' => $estimate->id, 'quantity' => 10, 'unit' => 'g'];
        $recipe = $this->recipes->save($this->user, $this->data, $this->recipe);

        expect($this->log->logRecipe($this->user, $recipe, 1, 'serving', Meal::Lunch, $this->today)->fresh()->is_estimate)->toBeTrue()
            ->and($this->log->logRecipe($this->user, $recipe, 50, 'g', Meal::Lunch, $this->today)->fresh()->is_estimate)->toBeTrue();
    });

    it('is not set when no ingredient is an estimate', function () {
        expect($this->log->logRecipe($this->user, $this->recipe, 1, 'serving', Meal::Lunch, $this->today)->is_estimate)->toBeFalse();
    });
});

describe('missing data', function () {
    it('carries a known partial fiber value together with the missing flag', function () {
        $labneh = Food::factory()->create(['carbs_g' => 5, 'fiber_g' => null]);
        $avocado = Food::factory()->create(['carbs_g' => 8.53, 'fiber_g' => 6.7]);
        $recipe = $this->recipes->save($this->user, [
            'name' => 'Dip', 'servings' => 1,
            'ingredients' => [
                ['food_id' => $labneh->id, 'quantity' => 100, 'unit' => 'g'],
                ['food_id' => $avocado->id, 'quantity' => 100, 'unit' => 'g'],
            ],
        ]);

        $entry = $this->log->logRecipe($this->user, $recipe, 1, 'serving', Meal::Snack, $this->today)->fresh();

        expect($entry->fiber_g)->toEqualWithDelta(6.7, 1e-6)
            ->and($entry->missing)->toBe(['fiber'])
            ->and($entry->nutrients()->isMissing('fiber'))->toBeTrue()
            ->and($entry->nutrients()->netCarbs())->toEqualWithDelta(6.83, 1e-6);
    });
});

describe('editing a recipe entry', function () {
    it('recalculates when the number of servings changes', function () {
        $entry = $this->log->logRecipe($this->user, $this->recipe, 1, 'serving', Meal::Dinner, $this->today);

        $updated = $this->log->update($entry, ['quantity' => 2])->fresh();

        expect($updated->calories)->toEqualWithDelta(713.4, 1e-6)->and($updated->grams)->toBeNull();
    });

    it('can switch from servings to grams', function () {
        $entry = $this->log->logRecipe($this->user, $this->recipe, 1, 'serving', Meal::Dinner, $this->today);

        $updated = $this->log->update($entry, ['quantity' => 260, 'unit' => 'g'])->fresh();

        expect($updated->unit)->toBe('g')->and($updated->grams)->toBe(260.0)->and($updated->calories)->toEqualWithDelta(713.4, 1e-6);
    });

    it('recalculates from the current recipe', function () {
        $entry = $this->log->logRecipe($this->user, $this->recipe, 1, 'serving', Meal::Dinner, $this->today);
        $this->recipes->save($this->user, [...$this->data, 'servings' => 2], $this->recipe);

        expect($entry->fresh()->calories)->toEqualWithDelta(356.7, 1e-6)
            ->and($this->log->update($entry->fresh(), ['quantity' => 1])->fresh()->calories)->toEqualWithDelta(713.4, 1e-6);
    });

    it('rejects a food unit for a recipe entry', function () {
        $entry = $this->log->logRecipe($this->user, $this->recipe, 1, 'serving', Meal::Dinner, $this->today);

        expect(fn () => $this->log->update($entry, ['unit' => 'kg']))->toThrow(UnknownUnitException::class);
    });
});

describe('after the recipe is deleted', function () {
    beforeEach(function () {
        $this->entry = $this->log->logRecipe($this->user, $this->recipe, 1, 'serving', Meal::Dinner, $this->today);
        $this->recipes->delete($this->recipe);
        $this->entry = $this->entry->fresh();
    });

    it('keeps the diary entry and its snapshot', function () {
        expect($this->entry)->not->toBeNull()
            ->and($this->entry->recipe_id)->toBeNull()
            ->and($this->entry->description)->toBe('Kofta')
            ->and($this->entry->calories)->toEqualWithDelta(356.7, 1e-6)
            ->and(app(DailySummaryService::class)->forDate($this->user, $this->today)->totals->calories)->toEqualWithDelta(356.7, 1e-6);
    });

    it('scales the snapshot when only the quantity changes', function () {
        $updated = $this->log->update($this->entry, ['quantity' => 3])->fresh();

        expect($updated->calories)->toEqualWithDelta(1070.1, 1e-6)
            ->and($updated->protein_g)->toEqualWithDelta(97.5, 1e-6)
            ->and($updated->grams)->toBeNull();
    });

    it('refuses a unit change', function () {
        expect(fn () => $this->log->update($this->entry, ['quantity' => 100, 'unit' => 'g']))->toThrow(LoggingException::class);
    });
});
