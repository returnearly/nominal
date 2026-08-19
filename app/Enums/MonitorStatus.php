<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitorStatus: string
{
    case Pending = 'pending';
    case Up = 'up';
    case Down = 'down';
    case Paused = 'paused';
}
