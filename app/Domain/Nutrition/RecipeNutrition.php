<?php

namespace App\Domain\Nutrition;

final readonly class RecipeNutrition
{
    public function __construct(
        public Nutrients $total,
        public Nutrients $perServing,
        public ?Nutrients $per100g,
        public float $servings,
        public float $rawWeightGrams,
        public float $finishedWeightGrams,
        /** false = finished weight assumed equal to the raw ingredient weight */
        public bool $finishedWeightIsMeasured,
    ) {}
}
