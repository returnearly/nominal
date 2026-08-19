<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum MonitorStatus: string implements HasColor
{
    case Pending = 'pending';
    case Up = 'up';
    case Down = 'down';
    case Paused = 'paused';

    public function getColor(): string
    {
        return match ($this) {
            self::Up => 'success',
            self::Down => 'danger',
            self::Pending => 'gray',
            self::Paused => 'purple',
        };
    }
}
