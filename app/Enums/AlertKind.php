<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertKind: string
{
    case Down = 'down';
    case Recovered = 'recovered';
    case Reminder = 'reminder';
}
