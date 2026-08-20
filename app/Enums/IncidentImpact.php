<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncidentImpact: string implements HasColor, HasLabel
{
    case None = 'none';
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Minor => 'Minor',
            self::Major => 'Major',
            self::Critical => 'Critical',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Minor => 'warning',
            self::Major => 'danger',
            self::Critical => 'danger',
        };
    }
}
