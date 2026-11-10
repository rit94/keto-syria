<?php

namespace App\Enums;

enum Sex: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return __('enums.sex.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
