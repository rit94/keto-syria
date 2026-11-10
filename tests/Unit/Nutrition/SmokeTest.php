<?php

use App\Domain\Nutrition\MacroTargets;
use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;

it('scales per-100g facts and computes remaining macros', function () {
    $calc = new NutritionCalculator;
    $egg = new Nutrients(143, 12.56, 0.72, 0.0, 9.51, 3.126);
    $eaten = $calc->forGrams($egg, 150);

    expect($eaten->calories)->toEqualWithDelta(214.5, 1e-9)
        ->and($eaten->netCarbs())->toEqualWithDelta(1.08, 1e-9);

    $left = $calc->remaining(new MacroTargets(1900, 130, 140, 35, 25), $eaten);
    expect($left->netCarbs)->toEqualWithDelta(23.92, 1e-9)->and($left->isLowerBound)->toBeFalse();
});
