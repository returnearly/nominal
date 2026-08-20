<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class CheckHeartbeat implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(Monitor $monitor): ProbeResult
    {
        $fresh = $monitor->last_heartbeat_at !== null
            && $monitor->last_heartbeat_at->gte(now()->subSeconds($monitor->interval_seconds));

        return new ProbeResult(
            success: $fresh,
            connected: $fresh,
            latencyMs: $fresh ? 0 : null,
            httpStatus: null,
            resolvedIp: null,
            certificateExpiresAt: null,
            message: $fresh ? null : 'Heartbeat not received',
            conditionResults: [],
        );
    }
}
