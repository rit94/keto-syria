<?php

namespace App\Livewire\Foods\Support;

use App\Models\FoodLogEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The user's own log history as "foods I eat": most recent and most frequent.
 * Reads only the user's entries of foods that still exist and are visible to them.
 */
class FoodHistory
{
    public const DAYS = 90;

    private const SCAN_LIMIT = 400;

    /** @var array<int, Collection<int, FoodLogEntry>> */
    private array $cache = [];

    /**
     * Latest entry per food, newest first.
     *
     * @return Collection<int, FoodLogEntry> each with ->food (units loaded) and ->times_logged
     */
    public function recent(User $user, int $limit = 8): Collection
    {
        return $this->latestPerFood($user)->take($limit)->values();
    }

    /**
     * Latest entry per food, most often logged first (ties: most recent first).
     *
     * @return Collection<int, FoodLogEntry>
     */
    public function frequent(User $user, int $limit = 8): Collection
    {
        return $this->latestPerFood($user)
            ->sortBy(fn (FoodLogEntry $entry) => [-$entry->times_logged, -$entry->id])
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, FoodLogEntry> */
    private function latestPerFood(User $user): Collection
    {
        return $this->cache[$user->id] ??= FoodLogEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('food_id')
            ->whereDate('logged_on', '>=', now()->subDays(self::DAYS)->toDateString())
            ->whereHas('food', fn ($query) => $query->visibleTo($user))
            ->with('food.units')
            ->orderByDesc('id')
            ->limit(self::SCAN_LIMIT)
            ->get()
            ->groupBy('food_id')
            ->map(function (Collection $entries) {
                $latest = $entries->first();
                $latest->times_logged = $entries->count();

                return $latest;
            })
            ->values();
    }
}
