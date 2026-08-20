<?php

declare(strict_types=1);

namespace App\Checking;

use App\Conditions\CheckContext;
use App\Models\Monitor;
use InvalidArgumentException;
use Throwable;

final class UdpChecker
{
    public function __construct(
        private readonly ConditionRunner $conditions,
        private readonly UdpTransport $transport,
    ) {}

    public function check(Monitor $monitor): ProbeResult
    {
        try {
            $address = SocketAddress::parse($monitor->target);
            $outcome = $this->transport->connect(
                $address->host,
                $address->port,
                $monitor->timeout_seconds,
                $monitor->ip_family,
                $monitor->request_body,
            );
        } catch (InvalidArgumentException|Throwable $exception) {
            $outcome = SocketOutcome::failed(null, $exception->getMessage());
        }

        $context = new CheckContext(
            responseTimeMs: $outcome->latencyMs,
            ip: $outcome->ip,
            connected: $outcome->connected,
            body: $outcome->body,
            rawBody: $outcome->body,
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
            responseBody: $outcome->body,
        );
    }
}
