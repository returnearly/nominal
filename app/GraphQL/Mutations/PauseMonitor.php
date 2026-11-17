<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\PauseMonitor as PauseMonitorAction;
use App\Models\Monitor;

final class PauseMonitor
{
    public function __construct(
        private readonly PauseMonitorAction $pauseMonitor,
    ) {}

    /**
     * @param  array{monitorId: string}  $args
     */
    public function __invoke(mixed $root, array $args): Monitor
    {
        $monitor = Monitor::query()->findOrFail($args['monitorId']);

        return $this->pauseMonitor->handle($monitor);
    }
}
