<?php

namespace App\Domain\Nutrition;

/** Target minus consumed. Negative values mean the target was exceeded. */
final readonly class RemainingMacros
{
    public function __construct(
        public float $calories,
        public float $protein,
        public float $fat,
        public float $carbs,
        public float $netCarbs,
        public bool $isLowerBound = false,
    ) {}

    /** @return array{calories: float, protein_g: float, fat_g: float, carbs_g: float, net_carbs_g: float, is_lower_bound: bool} */
    public function toArray(): array
    {
        return [
            'calories' => $this->calories,
            'protein_g' => $this->protein,
            'fat_g' => $this->fat,
            'carbs_g' => $this->carbs,
            'net_carbs_g' => $this->netCarbs,
            'is_lower_bound' => $this->isLowerBound,
        ];
    }
}
