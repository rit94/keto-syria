<?php

namespace App\Domain\Nutrition;

/** Daily targets for one person. Plain value object – no framework dependencies. */
final readonly class MacroTargets
{
    public function __construct(
        public float $calories,
        public float $protein,
        public float $fat,
        public float $carbs,
        public float $netCarbs,
    ) {}

    /** @return array{calories: float, protein_g: float, fat_g: float, carbs_g: float, net_carbs_g: float} */
    public function toArray(): array
    {
        return [
            'calories' => $this->calories,
            'protein_g' => $this->protein,
            'fat_g' => $this->fat,
            'carbs_g' => $this->carbs,
            'net_carbs_g' => $this->netCarbs,
        ];
    }
}
