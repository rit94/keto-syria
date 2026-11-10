<?php

namespace App\Enums;

enum LogSource: string
{
    case Manual = 'manual';
    case Text = 'text';
    case Voice = 'voice';
    case Image = 'image';
    case Coach = 'coach';
    case Plan = 'plan';

    public function label(): string
    {
        return __('enums.log_source.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
