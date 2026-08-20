<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class EndMonitorMaintenance implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Monitor $monitor): void
    {
        $windows = MaintenanceWindow::query()
            ->active()
            ->where('applies_to_all', false)
            ->whereHas('monitors', fn ($query) => $query->whereKey($monitor->id))
            ->with('monitors:id')
            ->get();

        foreach ($windows as $window) {
            if ($window->monitors->count() <= 1) {
                EndMaintenanceWindow::make()->handle($window);

                continue;
            }

            $window->monitors()->detach($monitor->id);
        }

        $monitor->unsetRelation('activeMaintenanceWindow');
    }
}
