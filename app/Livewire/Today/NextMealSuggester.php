<?php

namespace App\Livewire\Today;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\RemainingMacros;
use App\Livewire\Foods\Support\FoodHistory;
use App\Livewire\Foods\Support\UnitLabels;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Food\FoodSearchService;
use App\Services\Food\UnitResolver;
use App\Services\Logging\DailySummary;
use App\Services\Logging\FoodLogService;
use App\Services\Recipes\RecipeService;
use Illuminate\Support\Collection;
use Throwable;

/**
 * "What can I eat next?" – deterministic, no AI.
 *
 * Candidates: foods the user logs often, the popular keto-friendly catalogue foods and the user's
 * own recipes, each at its default amount (one default household unit, otherwise 100 g; one serving
 * for a recipe). The nutrition of every candidate is calculated by the engine (FoodLogService::preview
 * / RecipeService::nutrition). The only arithmetic here is the ranking score, which is never shown.
 *
 * A candidate is kept when it fits BOTH the remaining net carbs and the remaining calories.
 * Ranking favours whatever closes more of the remaining protein and fat gap for fewer net carbs.
 */
class NextMealSuggester
{
    private const CATALOGUE_POOL = 40;

    private const HISTORY_POOL = 12;

    private const RECIPE_POOL = 12;

    private const EPSILON = 1e-6;

    public function __construct(
        private readonly FoodLogService $log,
        private readonly RecipeService $recipes,
        private readonly FoodSearchService $search,
        private readonly FoodHistory $history,
        private readonly UnitResolver $units,
    ) {}

    /**
     * @return Collection<int, array{type: string, id: int, name: string, quantity: float, unit: string, unit_label: string, nutrients: Nutrients, is_estimate: bool, from_history: bool}>
     *                                                                                                                                                                                     empty when there are no targets or nothing fits
     */
    public function suggest(User $user, DailySummary $summary, int $limit = 5): Collection
    {
        $remaining = $summary->remaining;
        $targets = $summary->targets;

        if ($remaining === null || $targets === null || $remaining->calories <= 0) {
            return new Collection;
        }

        $candidates = [...$this->foodCandidates($user), ...$this->recipeCandidates($user)];

        $ranked = collect($candidates)
            ->filter(fn (array $c) => $this->fits($c['nutrients'], $remaining))
            ->map(function (array $c) use ($remaining, $targets) {
                $n = $c['nutrients'];
                $c['score'] = min($n->protein, max($remaining->protein, 0.0)) / max($targets->protein, 1.0)
                    + min($n->fat, max($remaining->fat, 0.0)) / max($targets->fat, 1.0)
                    - 0.25 * $n->netCarbs() / max($targets->netCarbs, 1.0);

                return $c;
            })
            ->sort(fn (array $a, array $b) => [$b['score'], $b['from_history'], $a['type'], $a['id']] <=> [$a['score'], $a['from_history'], $b['type'], $b['id']])
            ->values();

        // one suggestion per food family (raw / cooked / drained variants are the same idea)
        $seen = [];

        return $ranked
            ->filter(function (array $c) use (&$seen) {
                if (isset($seen[$c['family']])) {
                    return false;
                }

                return $seen[$c['family']] = true;
            })
            ->take($limit)
            ->map(fn (array $c) => collect($c)->except(['score', 'family'])->all())
            ->values();
    }

    private function fits(Nutrients $nutrients, RemainingMacros $remaining): bool
    {
        return $nutrients->calories > 0
            && $nutrients->netCarbs() <= max($remaining->netCarbs, 0.0) + self::EPSILON
            && $nutrients->calories <= $remaining->calories + self::EPSILON;
    }

    /** @return list<array<string, mixed>> */
    private function foodCandidates(User $user): array
    {
        $own = $this->history->frequent($user, self::HISTORY_POOL)->map->food->keyBy('id');

        $catalogue = $this->search->search('', $user, self::CATALOGUE_POOL)
            ->filter(fn (Food $food) => (bool) $food->is_keto_friendly)
            ->keyBy('id');

        $candidates = [];
        foreach ($own->union($catalogue) as $food) {
            $amount = UnitLabels::defaultAmount($food, $this->units);

            try {
                $nutrients = $this->log->preview($food, $amount['quantity'], $amount['unit']);
            } catch (Throwable) {
                continue; // a food whose default amount cannot be calculated is simply not suggested
            }

            $candidates[] = [
                'type' => 'food',
                'id' => $food->id,
                'family' => 'food:'.($food->parent_food_id ?? $food->id),
                'name' => $food->displayName(),
                'quantity' => $amount['quantity'],
                'unit' => $amount['unit'],
                'unit_label' => UnitLabels::for($food, $amount['unit']),
                'nutrients' => $nutrients,
                'is_estimate' => (bool) $food->is_estimate,
                'from_history' => $own->has($food->id),
            ];
        }

        return $candidates;
    }

    /** @return list<array<string, mixed>> */
    private function recipeCandidates(User $user): array
    {
        $recipes = Recipe::query()
            ->where('user_id', $user->id)
            ->with('ingredients.food')
            ->latest('id')
            ->limit(self::RECIPE_POOL)
            ->get();

        $candidates = [];
        foreach ($recipes as $recipe) {
            if ($recipe->ingredients->isEmpty() || $recipe->ingredients->contains(fn ($ingredient) => $ingredient->food === null)) {
                continue;
            }

            try {
                $nutrients = $this->recipes->nutrition($recipe)->perServing;
                $isEstimate = $this->recipes->isEstimate($recipe);
            } catch (Throwable) {
                continue;
            }

            $candidates[] = [
                'type' => 'recipe',
                'id' => $recipe->id,
                'family' => 'recipe:'.$recipe->id,
                'name' => $recipe->name,
                'quantity' => 1.0,
                'unit' => 'serving',
                'unit_label' => __('units.serving'),
                'nutrients' => $nutrients,
                'is_estimate' => $isEstimate,
                'from_history' => true,
            ];
        }

        return $candidates;
    }
}
