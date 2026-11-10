<?php

namespace App\Services\Logging;

use App\Domain\Nutrition\MacroTargets;
use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\RemainingMacros;
use App\Models\FoodLogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Everything the diary screen needs for one day. All numbers are full precision – round in the view. */
final readonly class DailySummary
{
    /**
     * @param  Collection<int, FoodLogEntry>  $entries  all entries of the day, oldest first
     * @param  array<string, array{entries: Collection<int, FoodLogEntry>, totals: Nutrients}>  $byMeal
     *                                                                                                   keyed by every Meal value in enum order; meals with nothing logged are present with zero totals
     * @param  ?MacroTargets  $targets  null when the user has no profile yet
     * @param  ?RemainingMacros  $remaining  null when there are no targets; negative values = over target
     */
    public function __construct(
        public CarbonImmutable $date,
        public Collection $entries,
        public array $byMeal,
        public Nutrients $totals,
        public ?MacroTargets $targets,
        public ?RemainingMacros $remaining,
    ) {}
}
