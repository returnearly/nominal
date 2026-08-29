<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Checking\SocketAddress;
use App\Checking\SocketOutcome;
use App\Checking\UdpTransport;
use App\Conditions\CheckContext;
use App\Models\Monitor;
use InvalidArgumentException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use Throwable;

final readonly class CheckUdp implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateCheckConditions $conditions,
        private UdpTransport $transport,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
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

        [$outcomes, $success, $message, $domainExpiresAt] = $this->conditions->handle(
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
            domainExpiresAt: $domainExpiresAt,
        );
    }
}
