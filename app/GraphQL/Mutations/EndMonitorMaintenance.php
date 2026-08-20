<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\EndMonitorMaintenance as EndMonitorMaintenanceAction;
use App\Models\Monitor;

final class EndMonitorMaintenance
{
    public function __construct(
        private readonly EndMonitorMaintenanceAction $endMonitorMaintenance,
    ) {}

    /**
     * @param  array{monitorId: string}  $args
     */
    public function __invoke(mixed $root, array $args): Monitor
    {
        $monitor = Monitor::query()->findOrFail($args['monitorId']);
        $this->endMonitorMaintenance->handle($monitor);

        return $monitor->fresh() ?? $monitor;
    }
}
