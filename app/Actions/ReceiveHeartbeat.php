<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Enums\HeartbeatSignal;
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

    public function handle(Monitor $monitor, HeartbeatSignal $signal = HeartbeatSignal::Finish, ?int $latencyMs = null): ?CheckResult
    {
        if ($signal === HeartbeatSignal::Start) {
            $monitor->heartbeat_started_at = now();
            $monitor->scheduleNextCheck();
            $monitor->save();

            return null;
        }

        $durationMs = $this->durationMs($monitor, $latencyMs);
        $success = $signal->succeeded();

        $monitor->last_heartbeat_at = now();
        $monitor->heartbeat_started_at = null;

        return $this->recorder->handle($monitor, null, new ProbeResult(
            success: $success,
            connected: $success,
            latencyMs: $durationMs,
            httpStatus: null,
            resolvedIp: null,
            certificateExpiresAt: null,
            message: $success ? null : 'Heartbeat reported an error',
            conditionResults: [],
        ));
    }

    private function durationMs(Monitor $monitor, ?int $latencyMs): ?int
    {
        if ($monitor->heartbeat_started_at === null) {
            return $latencyMs;
        }

        return max(0, (int) $monitor->heartbeat_started_at->diffInMilliseconds(now()));
    }
}
