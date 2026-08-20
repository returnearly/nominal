<?php

declare(strict_types=1);

namespace App\Enums;

enum UptimeWindow: string
{
    case OneHour = '1h';
    case TwentyFourHours = '24h';
    case SevenDays = '7d';
    case ThirtyDays = '30d';

    public function label(): string
    {
        return $this->value;
    }

    public function hours(): int
    {
        return match ($this) {
            self::OneHour => 1,
            self::TwentyFourHours => 24,
            self::SevenDays => 24 * 7,
            self::ThirtyDays => 24 * 30,
        };
    }
}
