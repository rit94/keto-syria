<?php

namespace App\Domain\Nutrition;

use InvalidArgumentException;

/**
 * An immutable bundle of nutrient amounts. The same type represents both
 * "facts per 100 g" and "amount actually eaten" – the meaning comes from context.
 *
 * Rules:
 *  - carbs is always TOTAL carbohydrate (fiber included).
 *  - fiber / saturatedFat may be null, meaning "unknown" (never silently zero).
 *  - Values keep full float precision. Round only for display (see rounded()).
 *  - `missing` lists nutrient keys that were unknown for at least one contributor,
 *    so a total can be shown as a lower bound instead of a false exact figure.
 */
final readonly class Nutrients
{
    public const FIBER = 'fiber';

    public const SATURATED_FAT = 'saturated_fat';

    /** @var list<string> */
    public array $missing;

    /**
     * @param  array<string, float>  $extras  optional additional nutrients, e.g. ['sodium_mg' => 120.0]
     * @param  list<string>  $missing  keys already known to be incomplete (carried through sums)
     */
    public function __construct(
        public float $calories,
        public float $protein,
        public float $carbs,
        public ?float $fiber,
        public float $fat,
        public ?float $saturatedFat = null,
        public array $extras = [],
        array $missing = [],
    ) {
        foreach (['calories' => $calories, 'protein' => $protein, 'carbs' => $carbs, 'fiber' => $fiber, 'fat' => $fat, 'saturatedFat' => $saturatedFat] as $name => $value) {
            if ($value !== null && (! is_finite($value) || $value < 0)) {
                throw new InvalidArgumentException("Nutrient [$name] must be a finite, non-negative number.");
            }
        }

        if ($fiber === null) {
            $missing[] = self::FIBER;
        }
        if ($saturatedFat === null) {
            $missing[] = self::SATURATED_FAT;
        }
        $missing = array_values(array_unique($missing));
        sort($missing);
        $this->missing = $missing;
    }

    public static function zero(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, 0.0, 0.0);
    }

    /**
     * Build from label values whose carbohydrate line may be on a "net" basis
     * (EU / UK / most Arabic-market labels exclude fiber) or a "total" basis (US labels include it).
     */
    public static function fromLabel(
        float $calories,
        float $protein,
        float $carbs,
        ?float $fiber,
        float $fat,
        ?float $saturatedFat = null,
        string $carbBasis = 'total',
        array $extras = [],
    ): self {
        if ($carbBasis === 'net') {
            // validate before adding: a negative label value must not be masked by the fiber
            if (! is_finite($carbs) || $carbs < 0) {
                throw new InvalidArgumentException('Nutrient [carbs] must be a finite, non-negative number.');
            }
            $carbs += $fiber ?? 0.0;
        } elseif ($carbBasis !== 'total') {
            throw new InvalidArgumentException("Unknown carbohydrate basis [$carbBasis].");
        }

        return new self($calories, $protein, $carbs, $fiber, $fat, $saturatedFat, $extras);
    }

    /**
     * Net carbohydrate = total carbohydrate − fiber, never below zero.
     * When fiber is unknown nothing is subtracted (conservative for keto) and
     * isMissing('fiber') is true.
     */
    public function netCarbs(): float
    {
        return max(0.0, $this->carbs - ($this->fiber ?? 0.0));
    }

    public function scale(float $factor): self
    {
        if (! is_finite($factor) || $factor < 0) {
            throw new InvalidArgumentException('Scale factor must be a finite, non-negative number.');
        }

        return new self(
            $this->calories * $factor,
            $this->protein * $factor,
            $this->carbs * $factor,
            $this->fiber === null ? null : $this->fiber * $factor,
            $this->fat * $factor,
            $this->saturatedFat === null ? null : $this->saturatedFat * $factor,
            array_map(fn (float $v) => $v * $factor, $this->extras),
            $this->missing,
        );
    }

    public function plus(self $other): self
    {
        $extras = $this->extras;
        foreach ($other->extras as $key => $value) {
            $extras[$key] = ($extras[$key] ?? 0.0) + $value;
        }

        return new self(
            $this->calories + $other->calories,
            $this->protein + $other->protein,
            $this->carbs + $other->carbs,
            self::addNullable($this->fiber, $other->fiber),
            $this->fat + $other->fat,
            self::addNullable($this->saturatedFat, $other->saturatedFat),
            $extras,
            [...$this->missing, ...$other->missing],
        );
    }

    /** @param iterable<self> $items */
    public static function sum(iterable $items): self
    {
        $total = self::zero();
        foreach ($items as $item) {
            $total = $total->plus($item);
        }

        return $total;
    }

    public function isMissing(string $key): bool
    {
        return in_array($key, $this->missing, true);
    }

    public function isComplete(): bool
    {
        return $this->missing === [];
    }

    /**
     * Calories implied by the macros (Atwater 4/4/9, fiber at 2 kcal/g).
     * Useful to sanity-check a label or a custom ingredient – not a replacement for $calories.
     */
    public function atwaterCalories(): float
    {
        $fiber = min($this->fiber ?? 0.0, $this->carbs);

        return 4 * $this->protein + 4 * ($this->carbs - $fiber) + 2 * $fiber + 9 * $this->fat;
    }

    /**
     * Full-precision array using the database column names.
     *
     * @return array{calories: float, protein_g: float, carbs_g: float, fiber_g: ?float, net_carbs_g: float, fat_g: float, saturated_fat_g: ?float, missing: list<string>}
     */
    public function toArray(): array
    {
        return [
            'calories' => $this->calories,
            'protein_g' => $this->protein,
            'carbs_g' => $this->carbs,
            'fiber_g' => $this->fiber,
            'net_carbs_g' => $this->netCarbs(),
            'fat_g' => $this->fat,
            'saturated_fat_g' => $this->saturatedFat,
            'missing' => $this->missing,
        ];
    }

    /**
     * Display rounding only: whole calories, one decimal for grams.
     *
     * @return array{calories: int, protein_g: float, carbs_g: float, fiber_g: ?float, net_carbs_g: float, fat_g: float, saturated_fat_g: ?float, missing: list<string>}
     */
    public function rounded(int $gramPrecision = 1): array
    {
        $r = fn (?float $v) => $v === null ? null : round($v, $gramPrecision);

        return [
            'calories' => (int) round($this->calories),
            'protein_g' => $r($this->protein),
            'carbs_g' => $r($this->carbs),
            'fiber_g' => $r($this->fiber),
            'net_carbs_g' => $r($this->netCarbs()),
            'fat_g' => $r($this->fat),
            'saturated_fat_g' => $r($this->saturatedFat),
            'missing' => $this->missing,
        ];
    }

    private static function addNullable(?float $a, ?float $b): ?float
    {
        return $a === null && $b === null ? null : ($a ?? 0.0) + ($b ?? 0.0);
    }
}
