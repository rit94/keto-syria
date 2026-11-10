<?php

namespace App\Enums;

enum Preparation: string
{
    case Drained = 'drained';
    case Undrained = 'undrained';

    public function label(): string
    {
        return __('enums.preparation.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
