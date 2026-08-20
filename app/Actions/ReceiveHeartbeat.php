<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Models\CheckResult;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ReceiveHeartbeat implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private readonly RecordCheckResult $recorder,
    ) {}

    public function handle(Monitor $monitor, ?int $latencyMs = null): CheckResult
    {
        $monitor->last_heartbeat_at = now();

        return $this->recorder->handle($monitor, null, new ProbeResult(
            success: true,
            connected: true,
            latencyMs: $latencyMs,
            httpStatus: null,
            resolvedIp: null,
            certificateExpiresAt: null,
            message: null,
            conditionResults: [],
        ));
    }
}
