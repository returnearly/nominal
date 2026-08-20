<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\RecordCheckResult;
use App\Checking\Checker;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunCheckJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public string $monitorId,
        public ?string $probeId = null,
    ) {}

    public function handle(Checker $checker, RecordCheckResult $recorder): void
    {
        $monitor = Monitor::query()->with('conditions')->find($this->monitorId);
        $probe = $this->probeId === null ? null : Probe::query()->find($this->probeId);

        if ($monitor === null || ! $monitor->enabled) {
            return;
        }

        if ($this->probeId !== null && ($probe === null || ! $probe->enabled)) {
            return;
        }

        $result = $checker->check($monitor);
        $recorder->handle($monitor, $probe, $result);
    }
}
