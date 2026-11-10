<?php

namespace App\Services\Ai\SmartLog;

use App\Enums\Meal;
use App\Services\Ai\Support\AiClock;
use Illuminate\Support\Str;

/**
 * A meal the user described (text, voice transcript or photo), resolved against the food
 * database and waiting for the user's review. Nothing is logged until
 * FoodDraftService::commit().
 *
 * Serialisable: toArray() / fromArray() hold ids and scalars only, so a draft can live in
 * Livewire component state. Everything that comes back from the client is treated as
 * untrusted: FoodDraftService::recalculate() / commit() recompute every status and every
 * number from the database, so editing the array in the browser cannot change what is logged
 * beyond picking foods, quantities, units, meal and date – which is exactly what the form does.
 *
 * Item shape (toArray()['items'][n]):
 *   key              stable id of the row within the draft
 *   spoken_name      the food as the user said it
 *   food_id          ?int   chosen food (null = nothing matched)
 *   food_name        ?string display name of the chosen food in the user's language
 *   candidates       [{food_id, name, score}] best matches, best first (for a "did you mean" picker)
 *   quantity         ?float
 *   unit             ?string unit key (g, kg, oz, ouqiya, or a unit of the food such as piece / tbsp)
 *   units            [{unit, label, grams}] units valid for the chosen food (for a select box)
 *   weighed_cooked   bool   the weight was measured after cooking (raw food with a cooked yield)
 *   cooking_method   ?string as understood from the description (display only)
 *   spoken_state     raw | cooked | unknown
 *   is_cooking_fat   bool   oil / ghee / butter used for cooking, listed as its own row
 *   status           resolved | ambiguous | unresolved | needs_quantity
 *   issues           list of machine keys, translate with __('coach.draft.issues.'.$key)
 *   nutrition        ?array Nutrients::toArray() from the engine (full precision; round in the view)
 *   is_estimate      bool   the food's values are an estimate
 *   ambiguous        bool   the match was uncertain when the draft was built
 *   food_confirmed / quantity_confirmed / state_confirmed   bool, set when the user edits that part
 */
final class FoodDraft
{
    public const RESOLVED = 'resolved';

    public const AMBIGUOUS = 'ambiguous';

    public const UNRESOLVED = 'unresolved';

    public const NEEDS_QUANTITY = 'needs_quantity';

    public const ORIGIN_TEXT = 'text';

    public const ORIGIN_IMAGE = 'image';

    private const ITEM_DEFAULTS = [
        'key' => '',
        'spoken_name' => '',
        'food_id' => null,
        'food_name' => null,
        'candidates' => [],
        'quantity' => null,
        'unit' => null,
        'units' => [],
        'weighed_cooked' => false,
        'cooking_method' => null,
        'spoken_state' => 'unknown',
        'is_cooking_fat' => false,
        'status' => self::UNRESOLVED,
        'issues' => [],
        'nutrition' => null,
        'is_estimate' => false,
        'ambiguous' => false,
        'food_confirmed' => false,
        'quantity_confirmed' => false,
        'state_confirmed' => false,
    ];

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $questions  clarification questions for the user
     * @param  ?array<string, mixed>  $totals  engine total of the rows that have nutrition
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $meal,
        public readonly string $date,
        public readonly array $questions = [],
        public readonly bool $requiresConfirmation = true,
        public readonly ?array $totals = null,
        public readonly bool $totalsPartial = false,
        public readonly string $origin = self::ORIGIN_TEXT,
        public readonly bool $mealInferred = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'meal' => $this->meal,
            'date' => $this->date,
            'questions' => $this->questions,
            'requires_confirmation' => $this->requiresConfirmation,
            'totals' => $this->totals,
            'totals_partial' => $this->totalsPartial,
            'origin' => $this->origin,
            'meal_inferred' => $this->mealInferred,
        ];
    }

    /** Rebuild from (untrusted) array state. Unknown keys are dropped and every value is type-checked. */
    public static function fromArray(array $data): self
    {
        $items = [];
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (is_array($item)) {
                $items[] = self::item($item);
            }
        }

        $meal = is_string($data['meal'] ?? null) ? Meal::tryFrom($data['meal'])?->value : null;
        $date = AiClock::parseDate(is_string($data['date'] ?? null) ? $data['date'] : null) ?? AiClock::today();

        return new self(
            items: $items,
            meal: $meal,
            date: $date->toDateString(),
            questions: array_values(array_filter(is_array($data['questions'] ?? null) ? $data['questions'] : [], 'is_string')),
            requiresConfirmation: (bool) ($data['requires_confirmation'] ?? true),
            totals: is_array($data['totals'] ?? null) ? $data['totals'] : null,
            totalsPartial: (bool) ($data['totals_partial'] ?? false),
            origin: ($data['origin'] ?? null) === self::ORIGIN_IMAGE ? self::ORIGIN_IMAGE : self::ORIGIN_TEXT,
            mealInferred: (bool) ($data['meal_inferred'] ?? false),
        );
    }

    /**
     * A clean item row: defaults filled in, types enforced.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function item(array $item): array
    {
        $item = array_intersect_key($item, self::ITEM_DEFAULTS) + self::ITEM_DEFAULTS;

        $number = fn (mixed $v): ?float => is_numeric($v) && is_finite((float) $v) ? (float) $v : null;
        $text = fn (mixed $v, int $max = 120): ?string => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        $candidates = [];
        foreach (is_array($item['candidates']) ? array_slice($item['candidates'], 0, 10) : [] as $candidate) {
            if (is_array($candidate) && is_numeric($candidate['food_id'] ?? null)) {
                $candidates[] = [
                    'food_id' => (int) $candidate['food_id'],
                    'name' => (string) ($text($candidate['name'] ?? null, 255) ?? ''),
                    'score' => (float) ($number($candidate['score'] ?? null) ?? 0.0),
                ];
            }
        }

        $units = [];
        foreach (is_array($item['units']) ? array_slice($item['units'], 0, 30) : [] as $unit) {
            if (is_array($unit) && is_string($unit['unit'] ?? null)) {
                $units[] = [
                    'unit' => $unit['unit'],
                    'label' => (string) ($unit['label'] ?? $unit['unit']),
                    'grams' => (float) ($number($unit['grams'] ?? null) ?? 0.0),
                ];
            }
        }

        return [
            'key' => $text($item['key'], 40) ?? (string) Str::ulid(),
            'spoken_name' => $text($item['spoken_name']) ?? '',
            'food_id' => is_numeric($item['food_id']) ? (int) $item['food_id'] : null,
            'food_name' => $text($item['food_name'], 255),
            'candidates' => $candidates,
            'quantity' => $number($item['quantity']),
            'unit' => $text($item['unit'], 30),
            'units' => $units,
            'weighed_cooked' => (bool) $item['weighed_cooked'],
            'cooking_method' => $text($item['cooking_method'], 60),
            'spoken_state' => in_array($item['spoken_state'], ['raw', 'cooked', 'unknown'], true) ? $item['spoken_state'] : 'unknown',
            'is_cooking_fat' => (bool) $item['is_cooking_fat'],
            'status' => in_array($item['status'], [self::RESOLVED, self::AMBIGUOUS, self::UNRESOLVED, self::NEEDS_QUANTITY], true) ? $item['status'] : self::UNRESOLVED,
            'issues' => array_values(array_filter(is_array($item['issues']) ? $item['issues'] : [], 'is_string')),
            'nutrition' => is_array($item['nutrition']) ? $item['nutrition'] : null,
            'is_estimate' => (bool) $item['is_estimate'],
            'ambiguous' => (bool) $item['ambiguous'],
            'food_confirmed' => (bool) $item['food_confirmed'],
            'quantity_confirmed' => (bool) $item['quantity_confirmed'],
            'state_confirmed' => (bool) $item['state_confirmed'],
        ];
    }

    // ───────────── editing helpers for the preview form (return a new draft) ─────────────
    // After any of these call FoodDraftService::recalculate() to refresh statuses and numbers.

    /**
     * Change a row. Allowed keys: food_id, quantity, unit, weighed_cooked.
     *
     * @param  array<string, mixed>  $changes
     */
    public function withItem(string $key, array $changes): self
    {
        $items = array_map(function (array $item) use ($key, $changes) {
            if ($item['key'] !== $key) {
                return $item;
            }

            if (array_key_exists('food_id', $changes)) {
                $item['food_id'] = is_numeric($changes['food_id']) ? (int) $changes['food_id'] : null;
                $item['food_confirmed'] = $item['food_id'] !== null;
            }
            if (array_key_exists('quantity', $changes)) {
                $item['quantity'] = is_numeric($changes['quantity']) ? (float) $changes['quantity'] : null;
                $item['quantity_confirmed'] = true;
            }
            if (array_key_exists('unit', $changes)) {
                $item['unit'] = is_string($changes['unit']) && $changes['unit'] !== '' ? $changes['unit'] : null;
                $item['quantity_confirmed'] = true;
            }
            if (array_key_exists('weighed_cooked', $changes)) {
                $item['weighed_cooked'] = (bool) $changes['weighed_cooked'];
                $item['state_confirmed'] = true;
            }

            return $item;
        }, $this->items);

        return $this->with(items: $items);
    }

    /** Confirm the suggested food of an ambiguous row as it is. */
    public function confirmFood(string $key): self
    {
        return $this->with(items: array_map(
            fn (array $item) => $item['key'] === $key && $item['food_id'] !== null ? ['food_confirmed' => true] + $item : $item,
            $this->items,
        ));
    }

    public function withoutItem(string $key): self
    {
        return $this->with(items: array_values(array_filter($this->items, fn (array $item) => $item['key'] !== $key)));
    }

    /** Add a row the user picked by hand from the food search. */
    public function withAddedItem(int $foodId, ?float $quantity = null, ?string $unit = null): self
    {
        return $this->with(items: [...$this->items, self::item([
            'food_id' => $foodId,
            'quantity' => $quantity,
            'unit' => $unit,
            'food_confirmed' => true,
            'quantity_confirmed' => true,
            'state_confirmed' => true,
        ])]);
    }

    public function withMeal(Meal $meal): self
    {
        return $this->with(meal: $meal->value, mealInferred: false);
    }

    public function withDate(string $date): self
    {
        return $this->with(date: (AiClock::parseDate($date) ?? AiClock::today())->toDateString());
    }

    /** True when commit() would accept the draft. */
    public function isCommittable(): bool
    {
        if ($this->items === [] || $this->meal === null) {
            return false;
        }

        foreach ($this->items as $item) {
            if (in_array($item['status'], [self::UNRESOLVED, self::NEEDS_QUANTITY], true)) {
                return false;
            }
        }

        return true;
    }

    /** Named-argument copy. */
    public function with(mixed ...$changes): self
    {
        return new self(...array_merge([
            'items' => $this->items,
            'meal' => $this->meal,
            'date' => $this->date,
            'questions' => $this->questions,
            'requiresConfirmation' => $this->requiresConfirmation,
            'totals' => $this->totals,
            'totalsPartial' => $this->totalsPartial,
            'origin' => $this->origin,
            'mealInferred' => $this->mealInferred,
        ], $changes));
    }
}
