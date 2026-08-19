<?php

declare(strict_types=1);

namespace App\Checking;

use App\Conditions\CheckContext;
use App\Models\Monitor;

final class PingChecker
{
    public function __construct(
        private readonly ConditionRunner $conditions,
        private readonly PingTransport $transport,
    ) {}

    public function check(Monitor $monitor): ProbeResult
    {
        $outcome = $this->transport->ping(
            $monitor->target,
            $monitor->timeout_seconds,
            $monitor->ip_family,
        );

        $context = new CheckContext(
            responseTimeMs: $outcome->latencyMs,
            ip: $outcome->ip,
            connected: $outcome->connected,
        );

        [$outcomes, $success, $message] = $this->conditions->run(
            $monitor,
            $context,
            $outcome->connected ? null : $outcome->message,
        );

        return new ProbeResult(
            success: $success,
            connected: $outcome->connected,
            latencyMs: $outcome->latencyMs,
            httpStatus: null,
            resolvedIp: $outcome->ip,
            certificateExpiresAt: null,
            message: $message,
            conditionResults: $outcomes,
        );
    }
}
