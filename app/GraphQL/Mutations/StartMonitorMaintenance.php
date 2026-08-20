<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\StartMonitorMaintenance as StartMonitorMaintenanceAction;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;

final class StartMonitorMaintenance
{
    public function __construct(
        private readonly StartMonitorMaintenanceAction $startMonitorMaintenance,
    ) {}

    /**
     * @param  array{monitorId: string, title?: string|null, message?: string|null, endsAt?: mixed}  $args
     */
    public function __invoke(mixed $root, array $args): MaintenanceWindow
    {
        $monitor = Monitor::query()->findOrFail($args['monitorId']);

        return $this->startMonitorMaintenance->handle($monitor, [
            'title' => $args['title'] ?? 'Maintenance',
            'message' => $args['message'] ?? null,
            'endsAt' => $args['endsAt'] ?? null,
        ]);
    }
}
