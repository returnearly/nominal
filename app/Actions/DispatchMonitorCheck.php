<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MonitorType;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use App\Models\Probe;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class DispatchMonitorCheck implements ActionsPatternInterface
{
    use ActionsPattern;

    public function forSaved(Monitor $monitor): int
    {
        if (! $monitor->enabled || ! $monitor->type->usesOutboundProbe()) {
            return 0;
        }

        return $this->handle($monitor);
    }

    public function handle(Monitor $monitor): int
    {
        if ($monitor->type === MonitorType::Heartbeat) {
            RunCheckJob::dispatch($monitor->id);
            $monitor->scheduleNextCheck()->save();

            return 1;
        }

        $dispatched = 0;

        $monitor->probes()
            ->where('enabled', true)
            ->each(function (Probe $probe) use ($monitor, &$dispatched): void {
                RunCheckJob::dispatch($monitor->id, $probe->id)->onQueue($probe->queueName());
                $dispatched++;
            });

        if ($dispatched > 0) {
            $monitor->scheduleNextCheck()->save();
        }

        return $dispatched;
    }
}
