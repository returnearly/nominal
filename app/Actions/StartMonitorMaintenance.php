<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class StartMonitorMaintenance implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Monitor $monitor, array $input = []): MaintenanceWindow
    {
        $window = SaveMaintenanceWindow::make()->handle([
            'title' => $input['title'] ?? 'Maintenance',
            'message' => $input['message'] ?? null,
            'startsAt' => $input['startsAt'] ?? $input['starts_at'] ?? now(),
            'endsAt' => $input['endsAt'] ?? $input['ends_at'] ?? null,
            'appliesToAll' => false,
            'monitorIds' => [$monitor->id],
        ]);

        $monitor->unsetRelation('activeMaintenanceWindow');

        return $window;
    }
}
