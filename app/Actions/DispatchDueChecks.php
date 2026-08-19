<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MonitorStatus;
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
                $probes = $monitor->probes->where('enabled', true);

                if ($probes->isEmpty()) {
                    return;
                }

                foreach ($probes as $probe) {
                    RunCheckJob::dispatch($monitor->id, $probe->id)->onQueue($probe->queueName());
                    $dispatched++;
                }

                $monitor->scheduleNextCheck()->save();
            });

        return $dispatched;
    }
}
