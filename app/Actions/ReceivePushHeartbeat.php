<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ConditionRunner;
use App\Checking\ProbeResult;
use App\Conditions\CheckContext;
use App\Models\CheckResult;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use RuntimeException;

final readonly class ReceivePushHeartbeat implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private readonly ConditionRunner $conditions,
        private readonly RecordCheckResult $recorder,
    ) {}

    public function handle(Monitor $monitor, ?int $latencyMs = null): CheckResult
    {
        $probe = $monitor->probes()->where('enabled', true)->first();

        if ($probe === null) {
            throw new RuntimeException('Push monitor has no enabled probe.');
        }

        $monitor->last_heartbeat_at = now();
        $monitor->loadMissing('conditions');

        $context = new CheckContext(
            responseTimeMs: $latencyMs,
            connected: true,
        );

        [$outcomes, $success, $message] = $this->conditions->run($monitor, $context, null);

        return $this->recorder->handle($monitor, $probe, new ProbeResult(
            success: $success,
            connected: true,
            latencyMs: $latencyMs,
            httpStatus: null,
            resolvedIp: null,
            certificateExpiresAt: null,
            message: $message,
            conditionResults: $outcomes,
        ));
    }
}
