<?php

namespace App\Enums;

enum FoodState: string
{
    case Raw = 'raw';
    case Cooked = 'cooked';
    case NotApplicable = 'na';

    public function label(): string
    {
        return __('enums.food_state.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
