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
        if ($monitor->heartbeatIsHung()) {
            return new ProbeResult(
                success: false,
                connected: false,
                latencyMs: $this->elapsedMs($monitor),
                httpStatus: null,
                resolvedIp: null,
                certificateExpiresAt: null,
                message: 'Heartbeat started but not finished',
                conditionResults: [],
            );
        }

        if ($monitor->heartbeatIsRunning() || $this->isFresh($monitor)) {
            return new ProbeResult(
                success: true,
                connected: true,
                latencyMs: $monitor->heartbeatIsRunning() ? $this->elapsedMs($monitor) : 0,
                httpStatus: null,
                resolvedIp: null,
                certificateExpiresAt: null,
                message: null,
                conditionResults: [],
            );
        }

        return new ProbeResult(
            success: false,
            connected: false,
            latencyMs: null,
            httpStatus: null,
            resolvedIp: null,
            certificateExpiresAt: null,
            message: 'Heartbeat not received',
            conditionResults: [],
        );
    }

    private function isFresh(Monitor $monitor): bool
    {
        return $monitor->last_heartbeat_at !== null
            && $monitor->last_heartbeat_at->gte(now()->subSeconds($monitor->interval_seconds));
    }

    private function elapsedMs(Monitor $monitor): ?int
    {
        if ($monitor->heartbeat_started_at === null) {
            return null;
        }

        return max(0, (int) $monitor->heartbeat_started_at->diffInMilliseconds(now()));
    }
}
