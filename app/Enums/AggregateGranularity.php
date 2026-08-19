<?php

declare(strict_types=1);

namespace App\Enums;

enum AggregateGranularity: string
{
    case Hour = 'hour';
    case Day = 'day';
}
