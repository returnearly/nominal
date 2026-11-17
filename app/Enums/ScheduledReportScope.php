<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScheduledReportScope: string implements HasColor, HasLabel
{
    case All = 'all';
    case Monitors = 'monitors';
    case Tags = 'tags';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'All monitors',
            self::Monitors => 'Selected monitors',
            self::Tags => 'Tags',
        };
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
