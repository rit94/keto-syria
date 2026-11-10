<?php

namespace App\Services\Profile;

use App\Domain\Nutrition\MacroTargets;
use App\Domain\Nutrition\Nutrients;
use App\Models\FoodLogEntry;
use App\Models\User;
use App\Services\Logging\DailySummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only look back over the diary for the progress screen.
 *
 * Day totals come from DailySummaryService (engine sums of stored snapshots); the average
 * is Nutrients::sum()->scale(1 / n) over the days that have at least one entry, so days the
 * user did not log do not drag the average towards zero.
 */
class NutritionHistory
{
    /** How far back the logging streak is searched. */
    public const int STREAK_LOOKBACK_DAYS = 730;

    public function __construct(private readonly DailySummaryService $summaries) {}

    /**
     * @return array{
     *     days: Collection<string, ?Nutrients>,
     *     targets: ?MacroTargets,
     *     logged_days: int,
     *     total_days: int,
     *     average: ?Nutrients,
     *     within_net_carbs: ?int,
     *     streak: int,
     * }
     *   days: 'Y-m-d' => totals for the last $days days (today included, ascending); null = nothing logged that day.
     *   average: mean of the logged days; null when nothing was logged.
     *   within_net_carbs: logged days at or under the net-carb target; null without a profile.
     *   streak: consecutive logged days ending today (or yesterday, while today is still empty).
     */
    public function forUser(User $user, int $days = 7): array
    {
        $days = max(1, $days);
        $today = CarbonImmutable::today();
        $from = $today->subDays($days - 1);

        $logged = $this->loggedDates($user, $from, $today);

        $totals = $this->summaries->range($user, $from, $today)
            ->map(fn (Nutrients $nutrients, string $date) => isset($logged[$date]) ? $nutrients : null);

        $loggedTotals = $totals->filter();
        $count = $loggedTotals->count();

        $targets = $user->profile()->first()?->targets();

        return [
            'days' => $totals,
            'targets' => $targets,
            'logged_days' => $count,
            'total_days' => $days,
            'average' => $count === 0 ? null : Nutrients::sum($loggedTotals)->scale(1 / $count),
            'within_net_carbs' => $targets === null
                ? null
                : $loggedTotals->filter(fn (Nutrients $day) => $day->netCarbs() <= $targets->netCarbs)->count(),
            'streak' => $this->streak($user, $today),
        ];
    }

    /** Consecutive days with at least one diary entry, counted back from today (or yesterday). */
    public function streak(User $user, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $logged = $this->loggedDates($user, $today->subDays(self::STREAK_LOOKBACK_DAYS), $today);

        // Today not logged yet does not break a streak that was alive yesterday.
        $day = isset($logged[$today->toDateString()]) ? $today : $today->subDay();

        $streak = 0;
        while (isset($logged[$day->toDateString()])) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /** @return array<string, true> keyed 'Y-m-d' */
    private function loggedDates(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return FoodLogEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('logged_on', '>=', $from->toDateString())
            ->whereDate('logged_on', '<=', $to->toDateString())
            ->toBase()
            ->distinct()
            ->pluck('logged_on')
            ->mapWithKeys(fn ($date) => [CarbonImmutable::parse($date)->toDateString() => true])
            ->all();
    }
}
