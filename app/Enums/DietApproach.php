<?php

namespace App\Enums;

enum DietApproach: string
{
    case StrictKeto = 'strict_keto';
    case StandardKeto = 'standard_keto';
    case LiberalLowCarb = 'liberal_low_carb';

    public function label(): string
    {
        return __('enums.diet_approach.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
