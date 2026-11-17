<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScheduledReportType: string implements HasColor, HasLabel
{
    case Uptime = 'uptime';

    public function getLabel(): string
    {
        return match ($this) {
            self::Uptime => 'Uptime',
        };
    }

    public function getColor(): string
    {
        return 'primary';
    }
}
