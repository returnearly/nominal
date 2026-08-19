<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Enums\MonitorStatus;
use App\Events\CheckCompleted;
use App\Events\MonitorStatusUpdated;
use App\Metrics\MetricsStore;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class RecordCheckResult implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateAlerting $alerting,
        private MetricsStore $metrics,
    ) {}

    public function handle(Monitor $monitor, Probe $probe, ProbeResult $result): CheckResult
    {
        $now = now();
        $previousStatus = $monitor->status;

        $checkResult = $monitor->checkResults()->create([
            'probe_id' => $probe->id,
            'checked_at' => $now,
            'success' => $result->success,
            'latency_ms' => $result->latencyMs,
            'http_status' => $result->httpStatus,
            'resolved_ip' => $result->resolvedIp,
            'certificate_expires_at' => $result->certificateExpiresAt,
            'message' => $result->message,
            'condition_results' => array_map(
                fn ($outcome): array => $outcome->toArray(),
                $result->conditionResults,
            ),
        ]);

        if ($result->success) {
            $monitor->consecutive_successes++;
            $monitor->consecutive_failures = 0;
            $monitor->status = MonitorStatus::Up;
        } else {
            $monitor->consecutive_failures++;
            $monitor->consecutive_successes = 0;
            $monitor->status = MonitorStatus::Down;
        }

        $statusChanged = $previousStatus !== $monitor->status;

        $monitor->last_checked_at = $now;

        if ($statusChanged) {
            $monitor->last_status_changed_at = $now;
        }

        $monitor->save();

        CheckCompleted::dispatch($monitor, $probe, $checkResult);

        if ($statusChanged) {
            MonitorStatusUpdated::dispatch($monitor, $previousStatus);
        }

        $this->metrics->record($monitor, $probe, $result);
        $this->alerting->handle($monitor, $result);

        return $checkResult;
    }
}
