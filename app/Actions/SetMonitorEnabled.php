<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SetMonitorEnabled implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Monitor $monitor, bool $enabled): Monitor
    {
        $monitor->update(['enabled' => $enabled]);

        return $monitor;
    }
}
