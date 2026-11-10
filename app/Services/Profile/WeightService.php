<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Models\WeightEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Weigh-ins. One entry per user per day; the most recent entry drives the profile's
 * current weight and therefore the calculated targets.
 */
class WeightService
{
    public function __construct(private readonly ProfileService $profiles) {}

    /**
     * Record (or overwrite) the weigh-in for a day.
     *
     * When the entry is the user's latest one, profile.weight_kg follows it and the
     * targets are recalculated (customised targets are left alone by recalculate()).
     *
     * @throws ValidationException
     */
    public function record(User $user, CarbonInterface $date, float $kg, ?string $note = null): WeightEntry
    {
        if ($kg < ProfileService::MIN_WEIGHT_KG || $kg > ProfileService::MAX_WEIGHT_KG) {
            throw ValidationException::withMessages([
                'weight_kg' => __('macros.validation.weight_range', [
                    'min' => ProfileService::MIN_WEIGHT_KG,
                    'max' => ProfileService::MAX_WEIGHT_KG,
                ]),
            ]);
        }

        $day = $date->toDateString();
        if ($day > Carbon::today()->toDateString()) {
            throw ValidationException::withMessages([
                'measured_on' => __('macros.validation.weight_date_future'),
            ]);
        }

        $kg = round($kg, 1);
        $note = ($note === null || trim($note) === '') ? null : trim($note);

        return DB::transaction(function () use ($user, $day, $kg, $note) {
            $entry = $user->weightEntries()->whereDate('measured_on', $day)->first()
                ?? new WeightEntry(['user_id' => $user->id, 'measured_on' => $day]);

            $entry->fill(['weight_kg' => $kg, 'note' => $note])->save();

            $this->syncProfileWeight($user);

            return $entry;
        });
    }

    /**
     * Delete a weigh-in. If it was the latest one, the profile falls back to the
     * previous entry's weight (and keeps its current weight when none is left).
     */
    public function delete(WeightEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $user = $entry->user;
            $entry->delete();

            if ($user !== null) {
                $this->syncProfileWeight($user);
            }
        });
    }

    /**
     * Weight history and trend over the last $days days (inclusive of today).
     *
     * - start_kg / current_kg: first and last entry inside the window (current_kg falls
     *   back to the profile weight when the window is empty).
     * - change_kg: current − start (negative = lost weight); null without entries.
     * - weekly_rate_kg: least-squares slope of weight over time × 7 (kg per week, negative
     *   = losing); null with fewer than 2 entries.
     * - remaining_kg: distance still to go towards the goal weight, 0 once reached.
     * - progress_pct: share of the way from the user's FIRST EVER weigh-in to the goal
     *   weight, clamped to 0–100; null without a goal or without any entry.
     *
     * @return array{entries: list<array{date: string, kg: float}>, start_kg: ?float, current_kg: ?float, change_kg: ?float, goal_kg: ?float, remaining_kg: ?float, weekly_rate_kg: ?float, progress_pct: ?float}
     */
    public function trend(User $user, int $days = 90): array
    {
        $days = max(1, $days);
        $from = Carbon::today()->subDays($days);

        $entries = $user->weightEntries()
            ->whereDate('measured_on', '>=', $from->toDateString())
            ->whereDate('measured_on', '<=', Carbon::today()->toDateString())
            ->orderBy('measured_on')
            ->get();

        $profile = $user->profile()->first();
        $goalKg = $profile?->goal_weight_kg;

        $startKg = $entries->first()?->weight_kg;
        $currentKg = $entries->last()?->weight_kg ?? $profile?->weight_kg;

        // Progress is measured from where the user began, not from the window edge.
        $baselineKg = $user->weightEntries()->orderBy('measured_on')->first()?->weight_kg;

        $remainingKg = null;
        $progressPct = null;
        if ($goalKg !== null && $currentKg !== null) {
            $remainingKg = abs($currentKg - $goalKg);

            if ($baselineKg !== null && abs($baselineKg - $goalKg) > 1e-9) {
                $total = $baselineKg - $goalKg;                 // signed: > 0 when the goal is to lose
                $done = $baselineKg - $currentKg;
                $progressPct = round(max(0.0, min(100.0, $done / $total * 100.0)), 1);

                if ($progressPct >= 100.0) {
                    $remainingKg = 0.0;                          // reached or passed the goal
                }
            }

            $remainingKg = round($remainingKg, 1);
        }

        return [
            'entries' => $entries
                ->map(fn (WeightEntry $e) => ['date' => $e->measured_on->toDateString(), 'kg' => $e->weight_kg])
                ->values()
                ->all(),
            'start_kg' => $startKg,
            'current_kg' => $currentKg,
            'change_kg' => $startKg === null ? null : round($currentKg - $startKg, 1),
            'goal_kg' => $goalKg,
            'remaining_kg' => $remainingKg,
            'weekly_rate_kg' => $this->weeklyRate($entries->all()),
            'progress_pct' => $progressPct,
        ];
    }

    /**
     * Ordinary least-squares slope of weight (kg) against time (days), × 7.
     * Uses every point in the window, so a single noisy weigh-in does not swing the rate.
     *
     * @param  array<int, WeightEntry>  $entries  ordered by date
     */
    private function weeklyRate(array $entries): ?float
    {
        $n = count($entries);
        if ($n < 2) {
            return null;
        }

        $origin = $entries[0]->measured_on->copy()->startOfDay();
        $xs = [];
        $ys = [];
        foreach ($entries as $entry) {
            $xs[] = (float) round($origin->diffInDays($entry->measured_on->copy()->startOfDay()));
            $ys[] = $entry->weight_kg;
        }

        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($ys) / $n;

        $covariance = 0.0;
        $variance = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $covariance += ($xs[$i] - $meanX) * ($ys[$i] - $meanY);
            $variance += ($xs[$i] - $meanX) ** 2;
        }

        if ($variance <= 0.0) {
            return null;
        }

        return round($covariance / $variance * 7.0, 3);
    }

    /** Point profile.weight_kg at the user's latest weigh-in and recalculate targets if it changed. */
    private function syncProfileWeight(User $user): void
    {
        $profile = $user->profile()->first();
        $latest = $user->weightEntries()->orderByDesc('measured_on')->first();

        if ($profile === null || $latest === null) {
            return;
        }

        if (abs($profile->weight_kg - $latest->weight_kg) < 1e-9) {
            return;
        }

        $profile->weight_kg = $latest->weight_kg;
        $this->profiles->recalculate($profile);
        $user->setRelation('profile', $profile);
    }
}
