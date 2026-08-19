<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use App\Models\Probe;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class DispatchMonitorCheck implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Monitor $monitor): int
    {
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
