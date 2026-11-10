<?php

use App\Domain\Nutrition\Nutrients;

describe('construction', function () {
    it('keeps the given values at full precision', function () {
        $n = new Nutrients(143.123456789, 12.56, 0.72, 0.1, 9.51, 3.126);

        expect($n->calories)->toBe(143.123456789)
            ->and($n->protein)->toBe(12.56)
            ->and($n->carbs)->toBe(0.72)
            ->and($n->fiber)->toBe(0.1)
            ->and($n->fat)->toBe(9.51)
            ->and($n->saturatedFat)->toBe(3.126)
            ->and($n->extras)->toBe([])
            ->and($n->missing)->toBe([])
            ->and($n->isComplete())->toBeTrue();
    });

    it('builds an all-zero, complete value', function () {
        $zero = Nutrients::zero();

        expect($zero->calories)->toBe(0.0)
            ->and($zero->fiber)->toBe(0.0)
            ->and($zero->saturatedFat)->toBe(0.0)
            ->and($zero->netCarbs())->toBe(0.0)
            ->and($zero->isComplete())->toBeTrue();
    });

    it('rejects negative values', function (array $args) {
        new Nutrients(...$args);
    })->with([
        'calories' => [[-1, 0, 0, 0, 0, 0]],
        'protein' => [[0, -0.001, 0, 0, 0, 0]],
        'carbs' => [[0, 0, -5, 0, 0, 0]],
        'fiber' => [[0, 0, 0, -2, 0, 0]],
        'fat' => [[0, 0, 0, 0, -3, 0]],
        'saturated fat' => [[0, 0, 0, 0, 0, -0.5]],
    ])->throws(InvalidArgumentException::class);

    it('rejects NaN and infinite values', function (array $args) {
        new Nutrients(...$args);
    })->with([
        'NaN calories' => [[NAN, 0, 0, 0, 0, 0]],
        'INF calories' => [[INF, 0, 0, 0, 0, 0]],
        '-INF protein' => [[0, -INF, 0, 0, 0, 0]],
        'NaN carbs' => [[0, 0, NAN, 0, 0, 0]],
        'INF fiber' => [[0, 0, 0, INF, 0, 0]],
        'NaN fat' => [[0, 0, 0, 0, NAN, 0]],
        'INF saturated fat' => [[0, 0, 0, 0, 0, INF]],
    ])->throws(InvalidArgumentException::class);

    it('is immutable', function () {
        $n = new Nutrients(100, 10, 5, 2, 4, 1);

        expect(fn () => $n->calories = 5.0)->toThrow(Error::class);
    });
});

describe('net carbs', function () {
    it('is total carbs minus fiber', function () {
        expect((new Nutrients(160, 2, 8.53, 6.7, 14.66, 2.13))->netCarbs())->toEqualWithDelta(1.83, 1e-12);
    });

    it('never goes below zero when fiber exceeds carbs', function () {
        expect((new Nutrients(10, 0, 2.0, 3.5, 0, 0))->netCarbs())->toBe(0.0);
    });

    it('subtracts nothing when fiber is unknown and flags it', function () {
        $n = new Nutrients(100, 1, 12.0, null, 1, 0);

        expect($n->netCarbs())->toBe(12.0)
            ->and($n->isMissing(Nutrients::FIBER))->toBeTrue()
            ->and($n->isComplete())->toBeFalse();
    });

    it('equals total carbs when fiber is a known zero, without flagging', function () {
        $n = new Nutrients(100, 1, 12.0, 0.0, 1, 0);

        expect($n->netCarbs())->toBe(12.0)->and($n->isMissing(Nutrients::FIBER))->toBeFalse();
    });
});

describe('fromLabel', function () {
    it('keeps carbs as given on a total basis', function () {
        $n = Nutrients::fromLabel(200, 5, 20.0, 6.0, 10, 2, 'total');

        expect($n->carbs)->toBe(20.0)->and($n->fiber)->toBe(6.0)->and($n->netCarbs())->toBe(14.0);
    });

    it('defaults to the total basis', function () {
        expect(Nutrients::fromLabel(200, 5, 20.0, 6.0, 10)->carbs)->toBe(20.0);
    });

    it('adds fiber back on a net basis so carbs is always total', function () {
        $n = Nutrients::fromLabel(200, 5, 14.0, 6.0, 10, 2, 'net');

        expect($n->carbs)->toBe(20.0)->and($n->fiber)->toBe(6.0)->and($n->netCarbs())->toBe(14.0);
    });

    it('gives the same net carbs for the same product labelled either way', function () {
        $eu = Nutrients::fromLabel(579, 21.2, 9.1, 12.5, 49.9, 3.8, 'net');
        $us = Nutrients::fromLabel(579, 21.2, 21.6, 12.5, 49.9, 3.8, 'total');

        expect($eu->carbs)->toEqualWithDelta($us->carbs, 1e-12)
            ->and($eu->netCarbs())->toEqualWithDelta($us->netCarbs(), 1e-12);
    });

    it('cannot add unknown fiber on a net basis: carbs stay as labelled and fiber is flagged', function () {
        $n = Nutrients::fromLabel(200, 5, 14.0, null, 10, null, 'net');

        expect($n->carbs)->toBe(14.0)
            ->and($n->fiber)->toBeNull()
            ->and($n->netCarbs())->toBe(14.0)
            ->and($n->missing)->toBe(['fiber', 'saturated_fat']);
    });

    it('passes extras through', function () {
        expect(Nutrients::fromLabel(1, 1, 1, 1, 1, 1, 'net', ['sodium_mg' => 120.0])->extras)->toBe(['sodium_mg' => 120.0]);
    });

    it('rejects an unknown basis', function () {
        Nutrients::fromLabel(200, 5, 14, 6, 10, 2, 'gross');
    })->throws(InvalidArgumentException::class);

    it('rejects negative label values', function () {
        Nutrients::fromLabel(200, 5, -1, 6, 10, 2, 'net');
    })->throws(InvalidArgumentException::class);
});

describe('scale', function () {
    it('multiplies every nutrient and extra', function () {
        $n = (new Nutrients(100, 10, 5, 2, 4, 1, ['sodium_mg' => 120.0]))->scale(1.5);

        expect($n->calories)->toBe(150.0)
            ->and($n->protein)->toBe(15.0)
            ->and($n->carbs)->toBe(7.5)
            ->and($n->fiber)->toBe(3.0)
            ->and($n->fat)->toBe(6.0)
            ->and($n->saturatedFat)->toBe(1.5)
            ->and($n->extras)->toBe(['sodium_mg' => 180.0]);
    });

    it('does not mutate the original', function () {
        $original = new Nutrients(100, 10, 5, 2, 4, 1);
        $original->scale(3);

        expect($original->calories)->toBe(100.0);
    });

    it('keeps unknown values unknown instead of turning them into zero', function () {
        $n = (new Nutrients(100, 10, 5, null, 4, null))->scale(2);

        expect($n->fiber)->toBeNull()
            ->and($n->saturatedFat)->toBeNull()
            ->and($n->missing)->toBe(['fiber', 'saturated_fat']);
    });

    it('keeps an inherited missing flag even when the value itself is a number', function () {
        $n = (new Nutrients(100, 10, 5, 2.0, 4, 1.0, [], ['fiber']))->scale(0.5);

        expect($n->fiber)->toBe(1.0)->and($n->missing)->toBe(['fiber']);
    });

    it('scaling by zero gives zero but keeps unknowns unknown', function () {
        $n = (new Nutrients(100, 10, 5, null, 4, 1))->scale(0);

        expect($n->calories)->toBe(0.0)->and($n->fiber)->toBeNull()->and($n->isMissing('fiber'))->toBeTrue();
    });

    it('rejects a negative, NaN or infinite factor', function (float $factor) {
        (new Nutrients(100, 10, 5, 2, 4, 1))->scale($factor);
    })->with([-1.0, -0.0001, NAN, INF, -INF])->throws(InvalidArgumentException::class);
});

describe('plus and sum', function () {
    it('adds two complete values', function () {
        $n = (new Nutrients(100, 10, 5, 2, 4, 1))->plus(new Nutrients(50.5, 1.25, 0.5, 0.25, 5, 3));

        expect($n->calories)->toBe(150.5)
            ->and($n->protein)->toBe(11.25)
            ->and($n->carbs)->toBe(5.5)
            ->and($n->fiber)->toBe(2.25)
            ->and($n->fat)->toBe(9.0)
            ->and($n->saturatedFat)->toBe(4.0)
            ->and($n->isComplete())->toBeTrue();
    });

    it('treats known + unknown fiber as a flagged lower bound', function () {
        $n = (new Nutrients(100, 10, 5, 2.0, 4, 1))->plus(new Nutrients(100, 10, 8, null, 4, 1));

        expect($n->fiber)->toBe(2.0)
            ->and($n->missing)->toBe(['fiber'])
            ->and($n->carbs)->toBe(13.0)
            // only the known 2 g of fiber can be subtracted
            ->and($n->netCarbs())->toBe(11.0);
    });

    it('keeps unknown + unknown as unknown', function () {
        $n = (new Nutrients(100, 10, 5, null, 4, null))->plus(new Nutrients(100, 10, 8, null, 4, null));

        expect($n->fiber)->toBeNull()
            ->and($n->saturatedFat)->toBeNull()
            ->and($n->missing)->toBe(['fiber', 'saturated_fat']);
    });

    it('tracks missing saturated fat independently of fiber', function () {
        $n = (new Nutrients(100, 10, 5, 2, 4, null))->plus(new Nutrients(100, 10, 8, 1, 4, 2.5));

        expect($n->saturatedFat)->toBe(2.5)->and($n->fiber)->toBe(3.0)->and($n->missing)->toBe(['saturated_fat']);
    });

    it('is commutative for values and flags', function () {
        $a = new Nutrients(100, 10, 5, null, 4, 1, ['sodium_mg' => 10.0]);
        $b = new Nutrients(20, 1, 3, 2, 0.5, null, ['potassium_mg' => 5.0]);

        expect($a->plus($b)->toArray())->toEqual($b->plus($a)->toArray())
            ->and($a->plus($b)->missing)->toBe(['fiber', 'saturated_fat']);
    });

    it('sums extras key by key, keeping keys only one side has', function () {
        $n = (new Nutrients(1, 1, 1, 1, 1, 1, ['sodium_mg' => 120.0, 'potassium_mg' => 300.0]))
            ->plus(new Nutrients(1, 1, 1, 1, 1, 1, ['sodium_mg' => 30.5, 'magnesium_mg' => 12.0]));

        expect($n->extras)->toBe(['sodium_mg' => 150.5, 'potassium_mg' => 300.0, 'magnesium_mg' => 12.0]);
    });

    it('sums an empty list to a complete zero', function () {
        $n = Nutrients::sum([]);

        expect($n->toArray())->toEqual(Nutrients::zero()->toArray())->and($n->isComplete())->toBeTrue();
    });

    it('sums any iterable', function () {
        $generator = (function () {
            yield new Nutrients(100, 10, 5, 2, 4, 1, ['sodium_mg' => 1.0]);
            yield new Nutrients(200, 20, 10, 4, 8, 2, ['sodium_mg' => 2.0]);
            yield new Nutrients(300, 30, 15, 6, 12, 3);
        })();

        $n = Nutrients::sum($generator);

        expect($n->calories)->toBe(600.0)
            ->and($n->protein)->toBe(60.0)
            ->and($n->fiber)->toBe(12.0)
            ->and($n->extras)->toBe(['sodium_mg' => 3.0]);
    });

    it('propagates a missing flag from any single item through a sum', function () {
        $n = Nutrients::sum([
            new Nutrients(100, 10, 5, 2, 4, 1),
            new Nutrients(100, 10, 5, null, 4, 1),
            new Nutrients(100, 10, 5, 2, 4, 1),
        ]);

        expect($n->fiber)->toBe(4.0)->and($n->missing)->toBe(['fiber'])->and($n->isComplete())->toBeFalse();
    });

    it('never reports a sum of only-unknown items as complete', function () {
        $n = Nutrients::sum([new Nutrients(100, 10, 5, null, 4, null)]);

        expect($n->isMissing('fiber'))->toBeTrue()
            ->and($n->isMissing('saturated_fat'))->toBeTrue()
            ->and($n->netCarbs())->toBe(5.0);
    });

    it('lists each missing key once, sorted', function () {
        $n = Nutrients::sum([
            new Nutrients(1, 1, 1, 1, 1, null),
            new Nutrients(1, 1, 1, null, 1, null),
            new Nutrients(1, 1, 1, null, 1, 1),
        ]);

        expect($n->missing)->toBe(['fiber', 'saturated_fat']);
    });
});

describe('precision', function () {
    it('does not round internally', function () {
        $n = (new Nutrients(143, 12.56, 0.72, 0.0, 9.51, 3.126))->scale(0.37);

        expect($n->calories)->toBe(143 * 0.37)
            ->and($n->protein)->toBe(12.56 * 0.37)
            ->and($n->saturatedFat)->toBe(3.126 * 0.37);
    });

    it('stays within 1e-9 when summing a thousand 0.1 g items', function () {
        $item = new Nutrients(0.1, 0.1, 0.1, 0.1, 0.1, 0.1, ['sodium_mg' => 0.1]);
        $n = Nutrients::sum(array_fill(0, 1000, $item));

        expect($n->calories)->toEqualWithDelta(100.0, 1e-9)
            ->and($n->protein)->toEqualWithDelta(100.0, 1e-9)
            ->and($n->carbs)->toEqualWithDelta(100.0, 1e-9)
            ->and($n->fiber)->toEqualWithDelta(100.0, 1e-9)
            ->and($n->fat)->toEqualWithDelta(100.0, 1e-9)
            ->and($n->saturatedFat)->toEqualWithDelta(100.0, 1e-9)
            ->and($n->extras['sodium_mg'])->toEqualWithDelta(100.0, 1e-9)
            ->and($n->netCarbs())->toEqualWithDelta(0.0, 1e-9);
    });

    it('scaling down and back up returns the original within 1e-9', function () {
        $original = new Nutrients(884, 0, 0, 0, 100, 13.808);
        $roundTrip = $original->scale(1 / 3)->scale(3);

        expect($roundTrip->calories)->toEqualWithDelta(884.0, 1e-9)
            ->and($roundTrip->saturatedFat)->toEqualWithDelta(13.808, 1e-9);
    });
});

describe('output', function () {
    it('toArray uses the database column names at full precision', function () {
        $n = new Nutrients(214.523456, 18.8444, 1.0811, 0.2611, 14.26549, null);

        expect($n->toArray())->toBe([
            'calories' => 214.523456,
            'protein_g' => 18.8444,
            'carbs_g' => 1.0811,
            'fiber_g' => 0.2611,
            'net_carbs_g' => 1.0811 - 0.2611,
            'fat_g' => 14.26549,
            'saturated_fat_g' => null,
            'missing' => ['saturated_fat'],
        ]);
    });

    it('rounded gives whole calories and one decimal for grams', function () {
        $n = new Nutrients(214.523456, 18.8444, 1.0811, 0.2611, 14.26549, 3.96);

        expect($n->rounded())->toBe([
            'calories' => 215,
            'protein_g' => 18.8,
            'carbs_g' => 1.1,
            'fiber_g' => 0.3,
            'net_carbs_g' => 0.8,
            'fat_g' => 14.3,
            'saturated_fat_g' => 4.0,
            'missing' => [],
        ]);
    });

    it('rounded accepts another gram precision', function () {
        $r = (new Nutrients(99.4, 18.8444, 1.0811, 0.2611, 14.26549, 3.96))->rounded(2);

        expect($r['calories'])->toBe(99)
            ->and($r['protein_g'])->toBe(18.84)
            ->and($r['fat_g'])->toBe(14.27)
            ->and($r['net_carbs_g'])->toBe(0.82);
    });

    it('rounded keeps unknown values null and reports them', function () {
        $r = (new Nutrients(100, 10, 5.55, null, 4, null))->rounded();

        expect($r['fiber_g'])->toBeNull()
            ->and($r['saturated_fat_g'])->toBeNull()
            ->and($r['net_carbs_g'])->toBe(5.6)
            ->and($r['missing'])->toBe(['fiber', 'saturated_fat']);
    });

    it('rounding is display only: the object is unchanged', function () {
        $n = new Nutrients(214.523456, 18.8444, 1.0811, 0.2611, 14.26549, 3.96);
        $n->rounded();

        expect($n->calories)->toBe(214.523456)->and($n->protein)->toBe(18.8444);
    });
});

describe('atwater sanity check', function () {
    it('computes 4/4/9 with fiber at 2 kcal/g', function () {
        // 4*10 + 4*(20-5) + 2*5 + 9*10
        expect((new Nutrients(0, 10, 20, 5, 10, 0))->atwaterCalories())->toBe(200.0);
    });

    it('treats unknown fiber as none', function () {
        expect((new Nutrients(0, 10, 20, null, 10, 0))->atwaterCalories())->toBe(210.0);
    });
});
