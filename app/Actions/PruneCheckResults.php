<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class PruneCheckResults implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(): int
    {
        $deleted = 0;

        Monitor::query()->each(function (Monitor $monitor) use (&$deleted): void {
            $cutoff = now()->subDays($monitor->retention_days);

            $deleted += $monitor->checkResults()
                ->where('checked_at', '<', $cutoff)
                ->delete();

            $monitor->checkAggregates()
                ->where('period_start', '<', $cutoff)
                ->delete();
        });

        return $deleted;
    }
}
