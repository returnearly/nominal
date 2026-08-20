<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncidentStatus: string implements HasColor, HasLabel
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
    case Scheduled = 'scheduled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Investigating => 'Investigating',
            self::Identified => 'Identified',
            self::Monitoring => 'Monitoring',
            self::Resolved => 'Resolved',
            self::Scheduled => 'Scheduled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Investigating => 'danger',
            self::Identified => 'warning',
            self::Monitoring => 'info',
            self::Resolved => 'success',
            self::Scheduled => 'purple',
        };
    }

    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }
}
