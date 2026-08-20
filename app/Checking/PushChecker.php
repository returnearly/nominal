<?php

declare(strict_types=1);

namespace App\Checking;

use App\Conditions\CheckContext;
use App\Models\Monitor;

final class PushChecker
{
    public function __construct(
        private readonly ConditionRunner $conditions,
    ) {}

    public function check(Monitor $monitor): ProbeResult
    {
        $fresh = $monitor->last_heartbeat_at !== null
            && $monitor->last_heartbeat_at->gte(now()->subSeconds($monitor->interval_seconds));

        $context = new CheckContext(
            responseTimeMs: $fresh ? 0 : null,
            connected: $fresh,
        );

        [$outcomes, $success, $message] = $this->conditions->run(
            $monitor,
            $context,
            $fresh ? null : 'Heartbeat not received',
        );

        return new ProbeResult(
            success: $success,
            connected: $fresh,
            latencyMs: $fresh ? 0 : null,
            httpStatus: null,
            resolvedIp: null,
            certificateExpiresAt: null,
            message: $message,
            conditionResults: $outcomes,
        );
    }
}
