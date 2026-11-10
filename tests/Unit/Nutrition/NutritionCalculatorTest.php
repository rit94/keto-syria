<?php

use App\Domain\Nutrition\MacroTargets;
use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Domain\Nutrition\RecipeNutrition;
use App\Domain\Nutrition\RemainingMacros;

beforeEach(function () {
    $this->calc = new NutritionCalculator;

    // per 100 g
    $this->egg = new Nutrients(143, 12.56, 0.72, 0.0, 9.51, 3.126);
    $this->oliveOil = new Nutrients(884, 0, 0, 0, 100, 13.808);
    $this->avocado = new Nutrients(160, 2.0, 8.53, 6.7, 14.66, 2.13);
    $this->beef = new Nutrients(250, 26.0, 0.0, 0.0, 15.0, 6.0);
    $this->labneh = new Nutrients(160, 8.0, 5.0, null, 12.0, null);
});

describe('forGrams (single ingredient)', function () {
    it('returns the per-100 g facts unchanged for 100 g', function () {
        expect($this->calc->forGrams($this->avocado, 100)->toArray())->toEqual($this->avocado->toArray());
    });

    it('scales linearly', function () {
        $n = $this->calc->forGrams($this->avocado, 150);

        expect($n->calories)->toEqualWithDelta(240.0, 1e-9)
            ->and($n->protein)->toEqualWithDelta(3.0, 1e-9)
            ->and($n->carbs)->toEqualWithDelta(12.795, 1e-9)
            ->and($n->fiber)->toEqualWithDelta(10.05, 1e-9)
            ->and($n->netCarbs())->toEqualWithDelta(2.745, 1e-9)
            ->and($n->fat)->toEqualWithDelta(21.99, 1e-9)
            ->and($n->saturatedFat)->toEqualWithDelta(3.195, 1e-9);
    });

    it('handles fractional grams without rounding', function () {
        $n = $this->calc->forGrams($this->oliveOil, 4.5);

        expect($n->calories)->toEqualWithDelta(39.78, 1e-9)->and($n->fat)->toEqualWithDelta(4.5, 1e-9);
    });

    it('returns zero for zero grams', function () {
        $n = $this->calc->forGrams($this->egg, 0);

        expect($n->calories)->toBe(0.0)->and($n->fat)->toBe(0.0);
    });

    it('scales extras', function () {
        $n = $this->calc->forGrams(new Nutrients(100, 1, 1, 1, 1, 1, ['sodium_mg' => 400.0]), 25);

        expect($n->extras['sodium_mg'])->toEqualWithDelta(100.0, 1e-9);
    });

    it('keeps unknown fiber / saturated fat unknown', function () {
        $n = $this->calc->forGrams($this->labneh, 50);

        expect($n->fiber)->toBeNull()
            ->and($n->saturatedFat)->toBeNull()
            ->and($n->netCarbs())->toEqualWithDelta(2.5, 1e-9)
            ->and($n->missing)->toBe(['fiber', 'saturated_fat']);
    });

    it('rejects negative, NaN and infinite grams', function (float $grams) {
        $this->calc->forGrams($this->egg, $grams);
    })->with([-1.0, -0.001, NAN, INF, -INF])->throws(InvalidArgumentException::class);
});

describe('forIngredients (multiple ingredients)', function () {
    it('sums ingredients given as positional pairs', function () {
        $n = $this->calc->forIngredients([[$this->egg, 100], [$this->oliveOil, 10], [$this->avocado, 50]]);

        expect($n->calories)->toEqualWithDelta(143 + 88.4 + 80, 1e-9)
            ->and($n->protein)->toEqualWithDelta(12.56 + 0 + 1.0, 1e-9)
            ->and($n->carbs)->toEqualWithDelta(0.72 + 0 + 4.265, 1e-9)
            ->and($n->fiber)->toEqualWithDelta(0 + 0 + 3.35, 1e-9)
            ->and($n->netCarbs())->toEqualWithDelta(0.72 + 0.915, 1e-9)
            ->and($n->fat)->toEqualWithDelta(9.51 + 10 + 7.33, 1e-9)
            ->and($n->saturatedFat)->toEqualWithDelta(3.126 + 1.3808 + 1.065, 1e-9)
            ->and($n->isComplete())->toBeTrue();
    });

    it('accepts named keys and mixed shapes', function () {
        $positional = $this->calc->forIngredients([[$this->egg, 100], [$this->oliveOil, 10]]);
        $named = $this->calc->forIngredients([
            ['per100g' => $this->egg, 'grams' => 100],
            [$this->oliveOil, 10],
        ]);

        expect($named->toArray())->toEqual($positional->toArray());
    });

    it('equals forGrams for a single ingredient', function () {
        $one = $this->calc->forIngredients([[$this->avocado, 73.5]]);

        expect($one->toArray())->toEqual($this->calc->forGrams($this->avocado, 73.5)->toArray());
    });

    it('returns zero for no ingredients', function () {
        expect($this->calc->forIngredients([])->toArray())->toEqual(Nutrients::zero()->toArray());
    });

    it('flags the total when one ingredient lacks data', function () {
        $n = $this->calc->forIngredients([[$this->avocado, 100], [$this->labneh, 100]]);

        expect($n->fiber)->toEqualWithDelta(6.7, 1e-9)
            ->and($n->saturatedFat)->toEqualWithDelta(2.13, 1e-9)
            ->and($n->missing)->toBe(['fiber', 'saturated_fat'])
            // labneh's 5 g carbs cannot be reduced by an unknown fiber
            ->and($n->netCarbs())->toEqualWithDelta(8.53 + 5.0 - 6.7, 1e-9);
    });

    it('sums extras across ingredients', function () {
        $a = new Nutrients(1, 1, 1, 1, 1, 1, ['sodium_mg' => 100.0]);
        $b = new Nutrients(1, 1, 1, 1, 1, 1, ['sodium_mg' => 50.0, 'potassium_mg' => 200.0]);

        $n = $this->calc->forIngredients([[$a, 200], [$b, 50]]);

        expect($n->extras['sodium_mg'])->toEqualWithDelta(225.0, 1e-9)
            ->and($n->extras['potassium_mg'])->toEqualWithDelta(100.0, 1e-9);
    });

    it('accepts a generator', function () {
        $n = $this->calc->forIngredients((function () {
            yield [$this->egg, 50];
            yield [$this->egg, 50];
        })());

        expect($n->calories)->toEqualWithDelta(143.0, 1e-9);
    });

    it('stays within 1e-9 when summing a thousand 0.1 g portions', function () {
        $n = $this->calc->forIngredients(array_fill(0, 1000, [$this->avocado, 0.1]));
        $expected = $this->calc->forGrams($this->avocado, 100);

        expect($n->calories)->toEqualWithDelta($expected->calories, 1e-9)
            ->and($n->protein)->toEqualWithDelta($expected->protein, 1e-9)
            ->and($n->carbs)->toEqualWithDelta($expected->carbs, 1e-9)
            ->and($n->fiber)->toEqualWithDelta($expected->fiber, 1e-9)
            ->and($n->netCarbs())->toEqualWithDelta($expected->netCarbs(), 1e-9)
            ->and($n->fat)->toEqualWithDelta($expected->fat, 1e-9)
            ->and($n->saturatedFat)->toEqualWithDelta($expected->saturatedFat, 1e-9);
    });

    it('rejects a negative ingredient weight', function () {
        $this->calc->forIngredients([[$this->egg, 100], [$this->oliveOil, -10]]);
    })->throws(InvalidArgumentException::class);
});

describe('recipe', function () {
    beforeEach(function () {
        // 500 g beef + 20 g olive oil + 200 g avocado = 720 g raw
        $this->ingredients = [[$this->beef, 500], [$this->oliveOil, 20], [$this->avocado, 200]];
        $this->totalCalories = 1250 + 176.8 + 320;       // 1746.8
        $this->totalProtein = 130 + 0 + 4;               // 134
        $this->totalCarbs = 0 + 0 + 17.06;
        $this->totalFiber = 13.4;
        $this->totalFat = 75 + 20 + 29.32;               // 124.32
    });

    it('returns whole-recipe totals', function () {
        $r = $this->calc->recipe($this->ingredients, 4);

        expect($r)->toBeInstanceOf(RecipeNutrition::class)
            ->and($r->total->calories)->toEqualWithDelta($this->totalCalories, 1e-9)
            ->and($r->total->protein)->toEqualWithDelta($this->totalProtein, 1e-9)
            ->and($r->total->carbs)->toEqualWithDelta($this->totalCarbs, 1e-9)
            ->and($r->total->fiber)->toEqualWithDelta($this->totalFiber, 1e-9)
            ->and($r->total->netCarbs())->toEqualWithDelta(3.66, 1e-9)
            ->and($r->total->fat)->toEqualWithDelta($this->totalFat, 1e-9)
            ->and($r->total->toArray())->toEqual($this->calc->forIngredients($this->ingredients)->toArray());
    });

    it('divides totals by the number of servings', function () {
        $r = $this->calc->recipe($this->ingredients, 4);

        expect($r->servings)->toBe(4.0)
            ->and($r->perServing->calories)->toEqualWithDelta($this->totalCalories / 4, 1e-9)
            ->and($r->perServing->protein)->toEqualWithDelta(33.5, 1e-9)
            ->and($r->perServing->netCarbs())->toEqualWithDelta(0.915, 1e-9)
            ->and($r->perServing->fat)->toEqualWithDelta(31.08, 1e-9);
    });

    it('supports fractional servings', function () {
        $r = $this->calc->recipe($this->ingredients, 2.5);

        expect($r->perServing->calories)->toEqualWithDelta($this->totalCalories / 2.5, 1e-9);
    });

    it('per serving equals the total for a single serving', function () {
        $r = $this->calc->recipe($this->ingredients, 1);

        expect($r->perServing->toArray())->toEqual($r->total->toArray());
    });

    it('all servings together add back up to the total', function () {
        $r = $this->calc->recipe($this->ingredients, 7);
        $sum = Nutrients::sum(array_fill(0, 7, $r->perServing));

        expect($sum->calories)->toEqualWithDelta($r->total->calories, 1e-9)
            ->and($sum->fat)->toEqualWithDelta($r->total->fat, 1e-9)
            ->and($sum->netCarbs())->toEqualWithDelta($r->total->netCarbs(), 1e-9);
    });

    it('uses the raw ingredient weight for per-100 g when no cooked weight was measured', function () {
        $r = $this->calc->recipe($this->ingredients, 4);

        expect($r->rawWeightGrams)->toBe(720.0)
            ->and($r->finishedWeightGrams)->toBe(720.0)
            ->and($r->finishedWeightIsMeasured)->toBeFalse()
            ->and($r->per100g->calories)->toEqualWithDelta($this->totalCalories / 7.2, 1e-9)
            ->and($r->per100g->protein)->toEqualWithDelta($this->totalProtein / 7.2, 1e-9)
            ->and($r->per100g->fat)->toEqualWithDelta($this->totalFat / 7.2, 1e-9);
    });

    it('uses the measured cooked weight for per-100 g when given', function () {
        $r = $this->calc->recipe($this->ingredients, 4, 600);

        expect($r->rawWeightGrams)->toBe(720.0)
            ->and($r->finishedWeightGrams)->toBe(600.0)
            ->and($r->finishedWeightIsMeasured)->toBeTrue()
            ->and($r->per100g->calories)->toEqualWithDelta($this->totalCalories / 6, 1e-9)
            ->and($r->per100g->protein)->toEqualWithDelta($this->totalProtein / 6, 1e-9)
            ->and($r->per100g->netCarbs())->toEqualWithDelta(3.66 / 6, 1e-9);
    });

    it('cooking changes density, never the totals or the per-serving values', function () {
        $raw = $this->calc->recipe($this->ingredients, 4);
        $cooked = $this->calc->recipe($this->ingredients, 4, 600);

        expect($cooked->total->toArray())->toEqual($raw->total->toArray())
            ->and($cooked->perServing->toArray())->toEqual($raw->perServing->toArray())
            ->and($cooked->per100g->calories)->toBeGreaterThan($raw->per100g->calories);
    });

    it('eating the whole finished weight by grams equals the recipe total', function () {
        $r = $this->calc->recipe($this->ingredients, 4, 600);
        $whole = $this->calc->forGrams($r->per100g, 600);

        expect($whole->calories)->toEqualWithDelta($r->total->calories, 1e-9)
            ->and($whole->fat)->toEqualWithDelta($r->total->fat, 1e-9)
            ->and($whole->fiber)->toEqualWithDelta($r->total->fiber, 1e-9);
    });

    it('handles a cooked weight heavier than the raw weight (added water)', function () {
        $r = $this->calc->recipe($this->ingredients, 4, 1440);

        expect($r->per100g->calories)->toEqualWithDelta($this->totalCalories / 14.4, 1e-9);
    });

    it('has no per-100 g value when there is no weight at all', function () {
        $r = $this->calc->recipe([], 2);

        expect($r->per100g)->toBeNull()
            ->and($r->rawWeightGrams)->toBe(0.0)
            ->and($r->total->calories)->toBe(0.0);
    });

    it('propagates missing data to total, per serving and per 100 g', function () {
        $r = $this->calc->recipe([[$this->beef, 200], [$this->labneh, 100]], 2, 250);

        expect($r->total->missing)->toBe(['fiber', 'saturated_fat'])
            ->and($r->perServing->missing)->toBe(['fiber', 'saturated_fat'])
            ->and($r->per100g->missing)->toBe(['fiber', 'saturated_fat'])
            ->and($r->perServing->isComplete())->toBeFalse();
    });

    it('accepts named ingredient keys', function () {
        $r = $this->calc->recipe([['per100g' => $this->beef, 'grams' => 500], ['per100g' => $this->oliveOil, 'grams' => 20]], 2);

        expect($r->rawWeightGrams)->toBe(520.0)->and($r->total->calories)->toEqualWithDelta(1426.8, 1e-9);
    });

    it('accepts a generator of ingredients', function () {
        $r = $this->calc->recipe((function () {
            yield [$this->beef, 500];
            yield [$this->oliveOil, 20];
        })(), 2);

        expect($r->rawWeightGrams)->toBe(520.0)->and($r->total->calories)->toEqualWithDelta(1426.8, 1e-9);
    });

    it('rejects zero, negative, NaN or infinite servings', function (float $servings) {
        $this->calc->recipe($this->ingredients, $servings);
    })->with([0.0, -1.0, NAN, INF])->throws(InvalidArgumentException::class);

    it('rejects a zero, negative, NaN or infinite cooked weight', function (float $weight) {
        $this->calc->recipe($this->ingredients, 4, $weight);
    })->with([0.0, -600.0, NAN, INF])->throws(InvalidArgumentException::class);
});

describe('forServings', function () {
    it('multiplies a per-serving value', function () {
        $perServing = $this->calc->recipe([[$this->beef, 500], [$this->oliveOil, 20]], 4)->perServing;
        $n = $this->calc->forServings($perServing, 1.5);

        expect($n->calories)->toEqualWithDelta(1426.8 / 4 * 1.5, 1e-9)
            ->and($n->protein)->toEqualWithDelta(130 / 4 * 1.5, 1e-9);
    });

    it('returns the same value for one serving and zero for none', function () {
        $perServing = new Nutrients(300, 20, 4, 1, 22, 8);

        expect($this->calc->forServings($perServing, 1)->toArray())->toEqual($perServing->toArray())
            ->and($this->calc->forServings($perServing, 0)->calories)->toBe(0.0);
    });

    it('keeps unknowns unknown', function () {
        expect($this->calc->forServings($this->labneh, 2)->fiber)->toBeNull();
    });

    it('rejects negative, NaN and infinite servings', function (float $servings) {
        $this->calc->forServings($this->egg, $servings);
    })->with([-1.0, NAN, INF])->throws(InvalidArgumentException::class);
});

describe('rawGramsFromCooked', function () {
    it('divides the cooked weight by the yield', function () {
        expect($this->calc->rawGramsFromCooked(140, 0.70))->toEqualWithDelta(200.0, 1e-9)
            ->and($this->calc->rawGramsFromCooked(150, 0.75))->toEqualWithDelta(200.0, 1e-9);
    });

    it('handles foods that gain weight when cooked (yield above 1)', function () {
        expect($this->calc->rawGramsFromCooked(250, 2.5))->toEqualWithDelta(100.0, 1e-9);
    });

    it('is the identity for a yield of 1 and zero for nothing eaten', function () {
        expect($this->calc->rawGramsFromCooked(123.4, 1.0))->toBe(123.4)
            ->and($this->calc->rawGramsFromCooked(0, 0.7))->toBe(0.0);
    });

    it('makes cooked-weight logging equal raw-weight logging of the same piece of meat', function () {
        $fromCooked = $this->calc->forGrams($this->beef, $this->calc->rawGramsFromCooked(140, 0.70));
        $fromRaw = $this->calc->forGrams($this->beef, 200);

        expect($fromCooked->calories)->toEqualWithDelta($fromRaw->calories, 1e-9)
            ->and($fromCooked->protein)->toEqualWithDelta($fromRaw->protein, 1e-9);
    });

    it('rejects a zero, negative, NaN or infinite yield', function (float $yield) {
        $this->calc->rawGramsFromCooked(100, $yield);
    })->with([0.0, -0.7, NAN, INF])->throws(InvalidArgumentException::class);

    it('rejects negative, NaN or infinite cooked grams', function (float $grams) {
        $this->calc->rawGramsFromCooked($grams, 0.7);
    })->with([-1.0, NAN, INF])->throws(InvalidArgumentException::class);
});

describe('dailyTotals', function () {
    it('sums the entries of a day', function () {
        $n = $this->calc->dailyTotals([
            $this->calc->forGrams($this->egg, 150),
            $this->calc->forGrams($this->beef, 200),
            $this->calc->forGrams($this->avocado, 100),
            $this->calc->forGrams($this->oliveOil, 15),
        ]);

        expect($n->calories)->toEqualWithDelta(214.5 + 500 + 160 + 132.6, 1e-9)
            ->and($n->protein)->toEqualWithDelta(18.84 + 52 + 2 + 0, 1e-9)
            ->and($n->carbs)->toEqualWithDelta(1.08 + 0 + 8.53 + 0, 1e-9)
            ->and($n->netCarbs())->toEqualWithDelta(1.08 + 1.83, 1e-9)
            ->and($n->fat)->toEqualWithDelta(14.265 + 30 + 14.66 + 15, 1e-9)
            ->and($n->isComplete())->toBeTrue();
    });

    it('is zero and complete for an empty day', function () {
        $n = $this->calc->dailyTotals([]);

        expect($n->calories)->toBe(0.0)->and($n->netCarbs())->toBe(0.0)->and($n->isComplete())->toBeTrue();
    });

    it('marks the day incomplete when any entry lacks data', function () {
        $n = $this->calc->dailyTotals([
            $this->calc->forGrams($this->egg, 100),
            $this->calc->forGrams($this->labneh, 100),
        ]);

        expect($n->missing)->toBe(['fiber', 'saturated_fat'])
            ->and($n->saturatedFat)->toEqualWithDelta(3.126, 1e-9);
    });

    it('does not depend on the order of entries', function () {
        $entries = [
            $this->calc->forGrams($this->egg, 33.3),
            $this->calc->forGrams($this->labneh, 71.9),
            $this->calc->forGrams($this->avocado, 12.1),
        ];

        $a = $this->calc->dailyTotals($entries);
        $b = $this->calc->dailyTotals(array_reverse($entries));

        expect($a->calories)->toEqualWithDelta($b->calories, 1e-9)
            ->and($a->netCarbs())->toEqualWithDelta($b->netCarbs(), 1e-9)
            ->and($a->missing)->toBe($b->missing);
    });
});

describe('remaining', function () {
    beforeEach(function () {
        $this->targets = new MacroTargets(calories: 1900, protein: 130, fat: 140, carbs: 35, netCarbs: 25);
    });

    it('is the full target when nothing was eaten', function () {
        $left = $this->calc->remaining($this->targets, Nutrients::zero());

        expect($left)->toBeInstanceOf(RemainingMacros::class)
            ->and($left->toArray())->toBe([
                'calories' => 1900.0,
                'protein_g' => 130.0,
                'fat_g' => 140.0,
                'carbs_g' => 35.0,
                'net_carbs_g' => 25.0,
                'is_lower_bound' => false,
            ]);
    });

    it('subtracts what was consumed, using net carbs for the net target', function () {
        $left = $this->calc->remaining($this->targets, new Nutrients(1200.5, 80.25, 20.0, 12.0, 95.5, 30));

        expect($left->calories)->toEqualWithDelta(699.5, 1e-9)
            ->and($left->protein)->toEqualWithDelta(49.75, 1e-9)
            ->and($left->fat)->toEqualWithDelta(44.5, 1e-9)
            ->and($left->carbs)->toEqualWithDelta(15.0, 1e-9)
            ->and($left->netCarbs)->toEqualWithDelta(17.0, 1e-9)
            ->and($left->isLowerBound)->toBeFalse();
    });

    it('is exactly zero when the target is met exactly', function () {
        $left = $this->calc->remaining($this->targets, new Nutrients(1900, 130, 35, 10, 140, 0));

        expect($left->calories)->toBe(0.0)->and($left->protein)->toBe(0.0)->and($left->fat)->toBe(0.0)->and($left->carbs)->toBe(0.0);
    });

    it('goes negative when a target is exceeded (never clamped)', function () {
        $left = $this->calc->remaining($this->targets, new Nutrients(2400, 150, 60, 5, 180, 50));

        expect($left->calories)->toEqualWithDelta(-500.0, 1e-9)
            ->and($left->protein)->toEqualWithDelta(-20.0, 1e-9)
            ->and($left->fat)->toEqualWithDelta(-40.0, 1e-9)
            ->and($left->carbs)->toEqualWithDelta(-25.0, 1e-9)
            ->and($left->netCarbs)->toEqualWithDelta(-30.0, 1e-9);
    });

    it('can be over on one macro and under on the others', function () {
        $left = $this->calc->remaining($this->targets, new Nutrients(1000, 50, 40, 2, 60, 10));

        expect($left->netCarbs)->toEqualWithDelta(-13.0, 1e-9)
            ->and($left->calories)->toBeGreaterThan(0)
            ->and($left->fat)->toBeGreaterThan(0);
    });

    it('flags a lower bound when consumed data is incomplete', function () {
        $left = $this->calc->remaining($this->targets, new Nutrients(500, 30, 10, null, 40, 10));

        expect($left->isLowerBound)->toBeTrue()
            // unknown fiber: all 10 g count against the net-carb target
            ->and($left->netCarbs)->toEqualWithDelta(15.0, 1e-9);
    });

    it('flags a lower bound when only saturated fat is unknown', function () {
        expect($this->calc->remaining($this->targets, new Nutrients(500, 30, 10, 2, 40, null))->isLowerBound)->toBeTrue();
    });

    it('flags a lower bound when a single entry of the day lacked data', function () {
        $consumed = $this->calc->dailyTotals([
            $this->calc->forGrams($this->egg, 100),
            $this->calc->forGrams($this->labneh, 100),
        ]);

        expect($this->calc->remaining($this->targets, $consumed)->isLowerBound)->toBeTrue();
    });
});
