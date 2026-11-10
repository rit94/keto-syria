<?php

namespace App\Enums;

enum WeightGoal: string
{
    case Lose = 'lose';
    case Maintain = 'maintain';
    case Gain = 'gain';

    public function label(): string
    {
        return __('enums.weight_goal.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
