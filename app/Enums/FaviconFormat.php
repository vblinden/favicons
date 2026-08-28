<?php

namespace App\Enums;

enum FaviconFormat: string
{
    case Png = 'png';
    case Webp = 'webp';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Png => 'image/png',
            self::Webp => 'image/webp',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
