<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\Probe;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ApplyProbeToMonitors implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Probe $probe): int
    {
        $monitorIds = Monitor::query()
            ->where('type', '!=', MonitorType::Heartbeat)
            ->pluck('id')
            ->all();

        if ($monitorIds === []) {
            return 0;
        }

        $probe->monitors()->syncWithoutDetaching($monitorIds);

        return count($monitorIds);
    }
}
