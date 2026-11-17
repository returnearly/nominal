<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\ResumeMonitor as ResumeMonitorAction;
use App\Models\Monitor;

final class ResumeMonitor
{
    public function __construct(
        private readonly ResumeMonitorAction $resumeMonitor,
    ) {}

    /**
     * @param  array{monitorId: string}  $args
     */
    public function __invoke(mixed $root, array $args): Monitor
    {
        $monitor = Monitor::query()->findOrFail($args['monitorId']);

        return $this->resumeMonitor->handle($monitor);
    }
}
