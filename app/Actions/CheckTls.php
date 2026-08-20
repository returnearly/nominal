<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Checking\SocketAddress;
use App\Checking\SocketOutcome;
use App\Checking\TlsTransport;
use App\Conditions\CheckContext;
use App\Models\Monitor;
use DateTimeImmutable;
use InvalidArgumentException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use Throwable;

final readonly class CheckTls implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateCheckConditions $conditions,
        private TlsTransport $transport,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
    {
        try {
            $address = SocketAddress::parse($monitor->target, 443);
            $outcome = $this->transport->connect(
                $address->host,
                $address->port,
                $monitor->timeout_seconds,
                $monitor->ip_family,
                $monitor->verify_tls,
                $monitor->request_body,
            );
        } catch (InvalidArgumentException|Throwable $exception) {
            $outcome = SocketOutcome::failed(null, $exception->getMessage());
        }

        $certificateSeconds = $outcome->certificateExpiresAt instanceof DateTimeImmutable
            ? $outcome->certificateExpiresAt->getTimestamp() - time()
            : null;

        $context = new CheckContext(
            responseTimeMs: $outcome->latencyMs,
            ip: $outcome->ip,
            connected: $outcome->connected,
            certificateExpirationSeconds: $certificateSeconds,
            body: $outcome->body,
            rawBody: $outcome->body,
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
            certificateExpiresAt: $outcome->certificateExpiresAt,
            message: $message,
            conditionResults: $outcomes,
            responseBody: $outcome->body,
        );
    }
}
