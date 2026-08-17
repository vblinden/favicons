<?php

namespace App\Enums;

enum FaviconTheme: string
{
    case Default = 'default';
    case Dark = 'dark';
    case Light = 'light';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
