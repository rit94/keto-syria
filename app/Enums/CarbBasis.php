<?php

namespace App\Enums;

enum CarbBasis: string
{
    case Total = 'total';
    case Net = 'net';

    public function label(): string
    {
        return __('enums.carb_basis.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
