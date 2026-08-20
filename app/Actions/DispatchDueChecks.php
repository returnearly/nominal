<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class DispatchDueChecks implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(): int
    {
        $dispatched = 0;

        Monitor::query()
            ->with(['probes', 'conditions'])
            ->where('enabled', true)
            ->where('status', '!=', MonitorStatus::Paused)
            ->where('next_check_at', '<=', now())
            ->each(function (Monitor $monitor) use (&$dispatched): void {
                $queued = $this->queue($monitor);

                if ($queued === 0) {
                    return;
                }

                $dispatched += $queued;
                $monitor->scheduleNextCheck()->save();
            });

        return $dispatched;
    }

    private function queue(Monitor $monitor): int
    {
        if ($monitor->type === MonitorType::Heartbeat) {
            RunCheckJob::dispatch($monitor->id);

            return 1;
        }

        $queued = 0;

        foreach ($monitor->probes->where('enabled', true) as $probe) {
            RunCheckJob::dispatch($monitor->id, $probe->id)->onQueue($probe->queueName());
            $queued++;
        }

        return $queued;
    }
}
