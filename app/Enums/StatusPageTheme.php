<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StatusPageTheme: string implements HasLabel
{
    case Dark = 'dark';
    case Light = 'light';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dark => 'Dark',
            self::Light => 'Light',
        };
    }
}
