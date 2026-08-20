<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\PingTransport;
use App\Checking\ProbeResult;
use App\Conditions\CheckContext;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class CheckPing implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateCheckConditions $conditions,
        private PingTransport $transport,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
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

        [$outcomes, $success, $message] = $this->conditions->handle(
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
