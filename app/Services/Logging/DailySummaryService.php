<?php

namespace App\Services\Logging;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Enums\Meal;
use App\Models\FoodLogEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Reads the diary. Totals are always summed by the engine from the entries' stored snapshots. */
class DailySummaryService
{
    public function __construct(private readonly NutritionCalculator $calculator) {}

    public function forDate(User $user, CarbonInterface $date): DailySummary
    {
        $entries = FoodLogEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('logged_on', $date->toDateString())
            ->with(['food', 'recipe'])
            ->orderBy('created_at')->orderBy('id')
            ->get();

        $byMeal = [];
        foreach (Meal::cases() as $meal) {
            $mealEntries = $entries->filter(fn (FoodLogEntry $entry) => $entry->meal === $meal)->values();
            $byMeal[$meal->value] = [
                'entries' => $mealEntries,
                'totals' => $this->calculator->dailyTotals($mealEntries->map->nutrients()),
            ];
        }

        $totals = $this->calculator->dailyTotals($entries->map->nutrients());
        $targets = $user->profile()->first()?->targets();

        return new DailySummary(
            date: CarbonImmutable::parse($date->toDateString()),
            entries: $entries,
            byMeal: $byMeal,
            totals: $totals,
            targets: $targets,
            remaining: $targets === null ? null : $this->calculator->remaining($targets, $totals),
        );
    }

    /**
     * Totals per day for an inclusive date range.
     *
     * @return Collection<string, Nutrients> keyed 'Y-m-d' in ascending order;
     *                                       days with no entries are present as zero
     */
    public function range(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $start = CarbonImmutable::parse($from->toDateString());
        $end = CarbonImmutable::parse($to->toDateString());

        $grouped = FoodLogEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('logged_on', '>=', $start->toDateString())
            ->whereDate('logged_on', '<=', $end->toDateString())
            ->get()
            ->groupBy(fn (FoodLogEntry $entry) => $entry->logged_on->format('Y-m-d'));

        $days = new Collection;
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $days->put($key, $this->calculator->dailyTotals(
                ($grouped->get($key) ?? new Collection)->map->nutrients()
            ));
        }

        return $days;
    }
}
