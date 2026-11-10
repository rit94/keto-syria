<?php

namespace App\Services\Ai\SmartLog;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Enums\Confidence;
use App\Enums\FoodState;
use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\User;
use App\Services\Ai\Support\AiClock;
use App\Services\Food\FoodSearchService;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The deterministic half of smart logging. The model only says what the user mentioned
 * (names, amounts, units, cooking words); this class decides which database food that is,
 * whether the unit exists for it, raw vs cooked, and asks the engine for every number.
 */
class FoodDraftService
{
    /** Issues that mean "the user should look at this row" even though it can be logged. */
    private const SOFT_ISSUES = ['ambiguous_match', 'raw_or_cooked_unknown', 'cooked_weight_unconvertible', 'visual_estimate'];

    public function __construct(
        private readonly FoodSearchService $search,
        private readonly UnitResolver $units,
        private readonly FoodLogService $log,
        private readonly NutritionCalculator $calculator,
    ) {}

    /**
     * Build a draft from what the model extracted.
     *
     * @param  list<array{name: string, alt_names?: list<string>, quantity: ?float, unit: ?string, cooking_method?: ?string, state?: string, is_cooking_fat?: bool}>  $extracted
     * @param  list<string>  $questions
     */
    public function build(
        User $user,
        array $extracted,
        ?Meal $meal,
        ?CarbonInterface $date,
        array $questions = [],
        string $origin = FoodDraft::ORIGIN_TEXT,
    ): FoodDraft {
        $items = array_map(fn (array $row) => $this->resolve($user, $row), array_values($extracted));

        if ($items === [] && $questions === []) {
            $questions = [__('coach.draft.no_food_found')];
        }

        $mealInferred = $meal === null;
        $meal ??= $this->mealForNow();

        return $this->finish($user, new FoodDraft(
            items: $items,
            meal: $meal->value,
            date: ($date === null ? AiClock::today() : AiClock::parseDate($date->toDateString()))->toDateString(),
            questions: $questions,
            origin: $origin,
            mealInferred: $mealInferred,
        ));
    }

    /**
     * Refresh statuses, issues, units and nutrition after the user edited the draft
     * (or after it came back from client state). Always call before showing numbers.
     */
    public function recalculate(User $user, FoodDraft $draft): FoodDraft
    {
        return $this->finish($user, FoodDraft::fromArray($draft->toArray()));
    }

    /**
     * Log the draft as one meal through FoodLogService::logMany (one transaction, shared group).
     * The draft is recalculated first; numbers held in the draft are never written.
     *
     * @return Collection<int, FoodLogEntry>
     *
     * @throws FoodDraftNotReadyException empty draft, no meal, or rows still unresolved / without a usable quantity
     */
    public function commit(User $user, FoodDraft $draft, LogSource $source): Collection
    {
        $draft = $this->recalculate($user, $draft);

        if ($draft->items === []) {
            throw new FoodDraftNotReadyException(FoodDraftNotReadyException::EMPTY, $draft);
        }

        $blocking = array_values(array_filter(
            $draft->items,
            fn (array $item) => in_array($item['status'], [FoodDraft::UNRESOLVED, FoodDraft::NEEDS_QUANTITY], true),
        ));
        if ($blocking !== []) {
            throw new FoodDraftNotReadyException(FoodDraftNotReadyException::UNRESOLVED_ITEMS, $draft, array_column($blocking, 'key'));
        }

        $meal = $draft->meal === null ? null : Meal::tryFrom($draft->meal);
        if ($meal === null) {
            throw new FoodDraftNotReadyException(FoodDraftNotReadyException::NO_MEAL, $draft);
        }

        $items = array_map(fn (array $item) => [
            'food_id' => $item['food_id'],
            'quantity' => $item['quantity'],
            'unit' => $item['unit'],
            'weighed_cooked' => $item['weighed_cooked'],
        ], $draft->items);

        return $this->log->logMany($user, $items, $meal, AiClock::parseDate($draft->date), $source);
    }

    // ───────────────────────────── resolution ─────────────────────────────

    /**
     * Match one extracted row to a database food.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed> draft item (not yet evaluated)
     */
    private function resolve(User $user, array $row): array
    {
        $limit = max(2, (int) config('ai.parser.candidates', 5));
        $names = array_values(array_unique(array_filter(
            [(string) ($row['name'] ?? ''), ...array_map('strval', (array) ($row['alt_names'] ?? []))],
            fn (string $name) => trim($name) !== '',
        )));

        // The words the user actually said win; alternative names only help when they match better.
        $best = collect();
        foreach ($names as $name) {
            $matches = $this->search->match($name, $user, $limit);
            if ($matches->isNotEmpty() && ($best->isEmpty() || $matches[0]['score'] > $best[0]['score'])) {
                $best = $matches;
            }
        }

        $unit = is_string($row['unit'] ?? null) && trim($row['unit']) !== '' ? UnitAliases::canonical($row['unit']) : null;
        $state = in_array($row['state'] ?? null, ['raw', 'cooked'], true) ? $row['state'] : 'unknown';

        $item = [
            'key' => (string) Str::ulid(),
            'spoken_name' => (string) ($row['name'] ?? ''),
            'quantity' => is_numeric($row['quantity'] ?? null) ? (float) $row['quantity'] : null,
            'unit' => $unit,
            'cooking_method' => $row['cooking_method'] ?? null,
            'spoken_state' => $state,
            'is_cooking_fat' => (bool) ($row['is_cooking_fat'] ?? false),
        ];

        if ($best->isEmpty()) {
            return FoodDraft::item($item);
        }

        $top = $best[0];
        $food = $this->variantForState($top['food'], $state, $best);

        $item['food_id'] = $food->id;
        $item['ambiguous'] = $this->isAmbiguous($best);
        $item['candidates'] = $best->map(fn (array $match) => [
            'food_id' => $match['food']->id,
            'name' => $match['food']->displayName($this->localeOf($user)),
            'score' => (float) $match['score'],
        ])->all();

        // A weight measured after cooking, for a food whose facts describe the raw weight.
        $item['weighed_cooked'] = $state === 'cooked'
            && $food->state === FoodState::Raw
            && $food->cooked_yield !== null
            && $unit !== null && isset(UnitResolver::UNIVERSAL[$unit]);

        return FoodDraft::item($item);
    }

    /**
     * Low score, or another unrelated food scoring (almost) as well, means the user must choose.
     *
     * @param  Collection<int, array{food: Food, score: float}>  $matches
     */
    private function isAmbiguous(Collection $matches): bool
    {
        $top = $matches[0];

        if ($top['score'] < (float) config('ai.parser.resolved_score', 0.8)) {
            return true;
        }

        $margin = (float) config('ai.parser.ambiguity_margin', 0.03);

        foreach ($matches->slice(1) as $other) {
            if ($other['score'] >= $top['score'] - $margin && ! $this->sameFamily($top['food'], $other['food'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prefer the raw / cooked variant of the matched food that fits what the user said, when the
     * database has one: first among the scored matches, otherwise a single unambiguous variant.
     *
     * @param  Collection<int, array{food: Food, score: float}>  $matches
     */
    private function variantForState(Food $food, string $state, Collection $matches): Food
    {
        $wanted = match ($state) {
            'cooked' => FoodState::Cooked,
            'raw' => FoodState::Raw,
            default => null,
        };

        if ($wanted === null || $food->state === $wanted || $food->state === FoodState::NotApplicable) {
            return $food;
        }

        foreach ($matches as $match) {
            if ($match['food']->state === $wanted && $this->sameFamily($food, $match['food'])) {
                return $match['food'];
            }
        }

        $root = $food->parent_food_id ?? $food->id;
        $family = Food::query()
            ->where(fn ($q) => $q->where('parent_food_id', $root)->orWhere('id', $root))
            ->where('id', '!=', $food->id)
            ->where('state', $wanted->value)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $food->user_id))
            ->limit(2)
            ->get();

        return $family->count() === 1 ? $family->first() : $food;
    }

    private function sameFamily(Food $a, Food $b): bool
    {
        return ($a->parent_food_id ?? $a->id) === ($b->parent_food_id ?? $b->id);
    }

    // ───────────────────────────── evaluation ─────────────────────────────

    /** Evaluate every row from database facts and total the draft. */
    private function finish(User $user, FoodDraft $draft): FoodDraft
    {
        $foods = Food::query()->visibleTo($user)->with('units')
            ->whereIn('id', array_filter(array_column($draft->items, 'food_id')))
            ->get()->keyBy('id');

        $items = [];
        $parts = [];
        $needsReview = $draft->origin === FoodDraft::ORIGIN_IMAGE || $draft->questions !== [] || $draft->items === [];

        foreach ($draft->items as $item) {
            [$item, $nutrients] = $this->evaluate($user, $item, $foods->get($item['food_id'] ?? 0), $draft->origin);

            if ($nutrients !== null) {
                $parts[] = $nutrients;
            }
            if ($item['status'] !== FoodDraft::RESOLVED || array_intersect($item['issues'], self::SOFT_ISSUES) !== []) {
                $needsReview = true;
            }
            $items[] = $item;
        }

        return $draft->with(
            items: $items,
            requiresConfirmation: $needsReview,
            totals: $parts === [] ? null : $this->calculator->dailyTotals($parts)->toArray(),
            totalsPartial: count($parts) !== count($items),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: array<string, mixed>, 1: ?Nutrients}
     */
    private function evaluate(User $user, array $item, ?Food $food, string $origin): array
    {
        $item['issues'] = [];
        $item['nutrition'] = null;
        $item['units'] = [];
        $item['is_estimate'] = false;

        if ($food === null) {
            $item['issues'][] = $item['food_id'] === null ? 'no_match' : 'food_not_accessible';
            $item['food_id'] = null;
            $item['food_name'] = null;
            $item['weighed_cooked'] = false;
            $item['status'] = FoodDraft::UNRESOLVED;

            return [$item, null];
        }

        $available = $this->units->unitsFor($food);
        $item['food_name'] = $food->displayName($this->localeOf($user));
        $item['units'] = array_map(fn (array $u) => ['unit' => $u['unit'], 'label' => $u['label'], 'grams' => $u['grams']], $available);
        $item['is_estimate'] = (bool) $food->is_estimate;

        if ($item['ambiguous'] && ! $item['food_confirmed']) {
            $item['issues'][] = 'ambiguous_match';
        }
        if ($item['is_estimate']) {
            $item['issues'][] = 'estimated_values';
        }
        if ($food->confidence === Confidence::Low) {
            $item['issues'][] = 'low_confidence';
        }
        if ($origin === FoodDraft::ORIGIN_IMAGE && ! $item['quantity_confirmed']) {
            $item['issues'][] = 'visual_estimate';
        }

        $unit = $item['unit'] === null ? null : UnitAliases::canonical($item['unit']);
        $item['unit'] = $unit;
        $blocker = null;

        if ($item['quantity'] === null || $item['quantity'] <= 0) {
            $blocker = 'missing_quantity';
        } elseif ($unit === null) {
            $blocker = 'missing_unit';
        } elseif (! in_array($unit, array_column($available, 'unit'), true)) {
            $blocker = 'unknown_unit';
        }

        $nutrients = null;

        if ($blocker === null) {
            $isMass = isset(UnitResolver::UNIVERSAL[$unit]);

            // weighed_cooked only means something for a weight of a raw food
            if ($item['weighed_cooked'] && (! $isMass || $food->state !== FoodState::Raw)) {
                $item['weighed_cooked'] = false;
            }
            if ($item['weighed_cooked'] && $food->cooked_yield === null) {
                $item['weighed_cooked'] = false;
                $item['issues'][] = 'cooked_weight_unconvertible';
            }
            if ($isMass && $food->state === FoodState::Raw && ! $item['state_confirmed'] && ! $item['weighed_cooked']) {
                if ($item['spoken_state'] === 'cooked') {
                    // said "cooked" but only raw facts without a yield are available
                    if (! in_array('cooked_weight_unconvertible', $item['issues'], true)) {
                        $item['issues'][] = 'cooked_weight_unconvertible';
                    }
                } elseif ($item['spoken_state'] === 'unknown' && $food->cooked_yield !== null) {
                    $item['issues'][] = 'raw_or_cooked_unknown';
                }
            }

            try {
                $nutrients = $this->log->preview($food, (float) $item['quantity'], $unit, (bool) $item['weighed_cooked']);
            } catch (UnknownUnitException) {
                $blocker = 'unknown_unit';
            } catch (InvalidQuantityException) {
                $blocker = 'missing_quantity';
            } catch (LoggingException) {
                $blocker = 'not_calculable';
            }
        }

        if ($blocker !== null) {
            $item['issues'][] = $blocker;
            $item['status'] = FoodDraft::NEEDS_QUANTITY;

            return [$item, null];
        }

        $item['nutrition'] = $nutrients->toArray();
        $item['status'] = in_array('ambiguous_match', $item['issues'], true) ? FoodDraft::AMBIGUOUS : FoodDraft::RESOLVED;

        return [$item, $nutrients];
    }

    /** A sensible default meal for the current time in Syria when nothing else says which meal it is. */
    private function mealForNow(): Meal
    {
        $hour = AiClock::now()->hour;

        return match (true) {
            $hour >= 4 && $hour < 11 => Meal::Breakfast,
            $hour >= 11 && $hour < 17 => Meal::Lunch,
            $hour >= 17 && $hour < 23 => Meal::Dinner,
            default => Meal::Snack,
        };
    }

    private function localeOf(User $user): string
    {
        return $user->locale ?: (string) config('app.locale', 'ar');
    }
}
