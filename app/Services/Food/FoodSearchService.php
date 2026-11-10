<?php

namespace App\Services\Food;

use App\Enums\FoodCategory;
use App\Models\Food;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds foods by (dialect) name. Candidates come from the database, ranking happens
 * in PHP so the behaviour is identical on SQLite and MySQL.
 *
 * Score bands (also returned by match()):
 *   1.00        exact normalized name / alias
 *   0.97        exact once the article "ال" is ignored
 *   0.85–0.95   a name / alias starts with the query
 *   ≤ 0.80      the query tokens are found inside a name / alias
 *   ≤ 0.48      only fuzzy (typo-tolerant) token matches
 */
class FoodSearchService
{
    /** Catalogue slugs shown first for an empty query, in this order. */
    public const POPULAR = [
        'egg-whole-raw', 'egg-boiled', 'chicken-breast-raw', 'chicken-thigh-skin-raw',
        'kebab-mince-regular', 'lamb-leg-lean-raw', 'beef-slices-lean-raw', 'tuna-oil-drained',
        'labneh', 'yogurt-whole', 'halloumi', 'white-cheese-baladi', 'olive-oil', 'ghee-animal',
        'butter', 'tahini', 'olives-green', 'avocado', 'cucumber', 'tomato', 'lettuce',
        'cauliflower-raw', 'zucchini-raw', 'eggplant-raw', 'walnuts', 'almonds', 'makdous-drained',
        'coffee', 'mate', 'tea',
    ];

    private const MIN_SCORE = 0.34;

    private const STRONG_SCORE = 0.6;

    private const CANDIDATE_LIMIT = 500;

    private const FUZZY_SCAN_LIMIT = 5000;

    /** @var array<string, float> */
    private array $similarityCache = [];

    /**
     * @return Collection<int, Food> ranked best first, only foods visible to $user
     */
    public function search(string $query, ?User $user, int $limit = 20, ?FoodCategory $category = null): Collection
    {
        $limit = max(1, $limit);
        $normalized = ArabicNormalizer::normalize($query);

        if ($normalized === '') {
            return $this->defaults($user, $limit, $category);
        }

        $ranked = array_slice($this->rank($normalized, $user, $category), 0, $limit);

        return $this->hydrate(array_column($ranked, 'id'));
    }

    /**
     * Scored candidates for free-text / AI resolution. A score of 1.0 means the
     * text is exactly a known name or alias of the food.
     *
     * @return Collection<int, array{food: Food, score: float}>
     */
    public function match(string $name, ?User $user, int $limit = 5): Collection
    {
        $normalized = ArabicNormalizer::normalize($name);
        if ($normalized === '') {
            return collect();
        }

        $ranked = array_slice($this->rank($normalized, $user, null), 0, max(1, $limit));
        $foods = $this->hydrate(array_column($ranked, 'id'))->keyBy('id');

        return collect($ranked)
            ->filter(fn (array $row) => $foods->has($row['id']))
            ->map(fn (array $row) => ['food' => $foods[$row['id']], 'score' => round($row['score'], 4)])
            ->values();
    }

    /**
     * @return list<array{id: int, score: float}>
     */
    private function rank(string $normalizedQuery, ?User $user, ?FoodCategory $category): array
    {
        $loose = ArabicNormalizer::stripArticle($normalizedQuery);
        $tokens = array_values(array_unique(explode(' ', $loose)));
        $popularity = array_flip(self::POPULAR);

        $base = fn (): Builder => Food::query()
            ->visibleTo($user)
            ->when($category, fn (Builder $q) => $q->where('foods.category', $category->value))
            ->select(['foods.id', 'foods.slug', 'foods.user_id', 'foods.parent_food_id', 'foods.search_text']);

        // Stage 1: rows that contain at least one query token.
        $rows = $base()
            ->where(function (Builder $q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('foods.search_text', 'like', '%'.$this->escapeLike($token).'%');
                }
            })
            ->limit(self::CANDIDATE_LIMIT)
            ->toBase()
            ->get();

        $scored = $this->scoreRows($rows, $normalizedQuery, $loose, $tokens, $popularity);

        // Stage 2: not enough solid hits – scan the remaining visible rows so that typos
        // which share no token with the stored text can still be matched fuzzily.
        $strong = count(array_filter($scored, fn (array $r) => $r['score'] >= self::STRONG_SCORE));
        if ($strong === 0 || count($scored) < 3) {
            $rest = $base()
                ->when($rows->isNotEmpty(), fn (Builder $q) => $q->whereNotIn('foods.id', $rows->pluck('id')->all()))
                ->orderByRaw('foods.user_id is null')
                ->orderBy('foods.id')
                ->limit(self::FUZZY_SCAN_LIMIT)
                ->toBase()
                ->get();
            $scored = [...$scored, ...$this->scoreRows($rest, $normalizedQuery, $loose, $tokens, $popularity)];
        }

        usort($scored, fn (array $a, array $b) => [$b['score'], $a['variant'], $a['popularity'], $a['length'], $a['id']]
            <=> [$a['score'], $b['variant'], $b['popularity'], $b['length'], $b['id']]);

        return array_map(fn (array $r) => ['id' => $r['id'], 'score' => $r['score']], $scored);
    }

    /**
     * @param  iterable<object>  $rows
     * @param  list<string>  $tokens
     * @param  array<string, int>  $popularity
     * @return list<array{id: int, score: float, variant: int, popularity: int, length: int}>
     */
    private function scoreRows(iterable $rows, string $strict, string $loose, array $tokens, array $popularity): array
    {
        $scored = [];
        foreach ($rows as $row) {
            $terms = FoodIndexer::terms($row->search_text);
            $score = $this->score($terms, $strict, $loose, $tokens);
            if ($score < self::MIN_SCORE) {
                continue;
            }
            $scored[] = [
                'id' => (int) $row->id,
                'score' => $score,
                'variant' => $row->parent_food_id === null ? 0 : 1,
                'popularity' => $popularity[$row->slug ?? ''] ?? PHP_INT_MAX,
                'length' => mb_strlen($terms[0] ?? '', 'UTF-8'),
            ];
        }

        return $scored;
    }

    /**
     * @param  list<string>  $terms  normalized names / aliases of one food
     * @param  list<string>  $tokens  article-stripped query tokens
     */
    private function score(array $terms, string $strict, string $loose, array $tokens): float
    {
        $best = 0.0;
        $looseLength = mb_strlen($loose, 'UTF-8');

        foreach ($terms as $term) {
            if ($term === $strict) {
                return 1.0;
            }

            $looseTerm = ArabicNormalizer::stripArticle($term);
            if ($looseTerm === $loose) {
                $best = max($best, 0.97);

                continue;
            }

            $ratio = $looseLength / max(1, mb_strlen($looseTerm, 'UTF-8'));
            if (str_starts_with($looseTerm, $loose.' ')) {
                $best = max($best, 0.90 + 0.05 * $ratio);
            } elseif ($looseLength >= 2 && str_starts_with($looseTerm, $loose)) {
                $best = max($best, 0.85 + 0.04 * $ratio);
            } elseif ($best < 0.8) {
                $best = max($best, 0.8 * $this->tokenScore($tokens, explode(' ', $looseTerm)));
            }
        }

        return $best;
    }

    /**
     * How well the query tokens are covered by the tokens of one term (0..1).
     *
     * @param  list<string>  $queryTokens
     * @param  list<string>  $termTokens
     */
    private function tokenScore(array $queryTokens, array $termTokens): float
    {
        $total = 0.0;
        $matched = 0;

        foreach ($queryTokens as $queryToken) {
            $quality = 0.0;
            $queryLength = mb_strlen($queryToken, 'UTF-8');

            foreach ($termTokens as $termToken) {
                if ($termToken === $queryToken) {
                    $quality = 1.0;
                    break;
                }
                $termLength = mb_strlen($termToken, 'UTF-8');
                if ($queryLength >= 2 && str_starts_with($termToken, $queryToken)) {
                    $quality = max($quality, 0.85);
                } elseif ($termLength >= 3 && str_starts_with($queryToken, $termToken)) {
                    $quality = max($quality, 0.75);
                } elseif ($queryLength >= 3 && str_contains($termToken, $queryToken)) {
                    $quality = max($quality, 0.70);
                } elseif ($quality < 0.6) {
                    $quality = max($quality, 0.6 * $this->similarity($queryToken, $termToken));
                }
            }

            if ($quality > 0.0) {
                $matched++;
                $total += $quality;
            }
        }

        $count = count($queryTokens);
        if ($count === 0 || $matched < (int) ceil($count / 2)) {
            return 0.0;
        }

        $coverage = 0.85 + 0.15 * min(1.0, $count / max(1, count($termTokens)));

        return ($total / $count) * $coverage;
    }

    /**
     * Typo-tolerant similarity of two tokens (0 when they are too far apart).
     * Allowed edit distance: 0 for tokens up to 2 letters, 1 up to 5 letters, 2 beyond.
     */
    private function similarity(string $a, string $b): float
    {
        $key = $a."\0".$b;
        if (isset($this->similarityCache[$key])) {
            return $this->similarityCache[$key];
        }

        $charsA = mb_str_split($a, 1, 'UTF-8');
        $charsB = mb_str_split($b, 1, 'UTF-8');
        $lengthA = count($charsA);
        $lengthB = count($charsB);
        $shorter = min($lengthA, $lengthB);
        $longer = max($lengthA, $lengthB);
        $allowed = $shorter <= 2 ? 0 : ($shorter <= 5 ? 1 : 2);

        if ($allowed === 0 || $longer - $shorter > $allowed) {
            return $this->similarityCache[$key] = 0.0;
        }

        $distance = $this->levenshtein($charsA, $charsB);

        return $this->similarityCache[$key] = $distance > $allowed ? 0.0 : 1.0 - $distance / $longer;
    }

    /**
     * Multibyte-safe Levenshtein distance (PHP's levenshtein() counts bytes, which
     * doubles every Arabic letter).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function levenshtein(array $a, array $b): int
    {
        $previous = range(0, count($b));

        foreach ($a as $i => $charA) {
            $current = [$i + 1];
            foreach ($b as $j => $charB) {
                $current[] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($charA === $charB ? 0 : 1),
                );
            }
            $previous = $current;
        }

        return $previous[count($b)];
    }

    /**
     * Empty query: the user's own foods first (newest first), then popular catalogue
     * staples, then the rest of the catalogue (base foods before variants).
     *
     * @return Collection<int, Food>
     */
    private function defaults(?User $user, int $limit, ?FoodCategory $category): Collection
    {
        $popularity = array_flip(self::POPULAR);

        $rows = Food::query()
            ->visibleTo($user)
            ->when($category, fn (Builder $q) => $q->where('foods.category', $category->value))
            ->select(['foods.id', 'foods.slug', 'foods.user_id', 'foods.parent_food_id'])
            ->limit(self::FUZZY_SCAN_LIMIT)
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'id' => (int) $row->id,
                'popularity' => $popularity[$row->slug ?? ''] ?? PHP_INT_MAX,
                'own' => $row->user_id === null ? 1 : 0,
                'variant' => $row->parent_food_id === null ? 0 : 1,
            ])
            ->all();

        $ownQuota = (int) ceil($limit / 4);
        $own = array_values(array_filter($rows, fn (array $r) => $r['own'] === 0));
        usort($own, fn (array $a, array $b) => $b['id'] <=> $a['id']);
        $own = array_slice($own, 0, $ownQuota);

        $catalogue = array_values(array_filter($rows, fn (array $r) => $r['own'] === 1));
        usort($catalogue, fn (array $a, array $b) => [$a['popularity'], $a['variant'], $a['id']] <=> [$b['popularity'], $b['variant'], $b['id']]);

        $ids = array_slice(array_column([...$own, ...$catalogue], 'id'), 0, $limit);

        return $this->hydrate($ids);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Food> in the order of $ids
     */
    private function hydrate(array $ids): Collection
    {
        if ($ids === []) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $order = array_flip($ids);

        return Food::query()
            ->with('units')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Food $food) => $order[$food->id])
            ->values();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
