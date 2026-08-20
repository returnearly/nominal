<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\DnsOutcome;
use App\Checking\DnsTransport;
use App\Checking\ProbeResult;
use App\Conditions\CheckContext;
use App\Enums\DnsQueryType;
use App\Models\Monitor;
use InvalidArgumentException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use Throwable;

final readonly class CheckDns implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateCheckConditions $conditions,
        private DnsTransport $transport,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
    {
        $name = trim((string) $monitor->dns_query_name);
        $type = $monitor->dns_query_type ?? DnsQueryType::A;

        try {
            if ($name === '') {
                throw new InvalidArgumentException('DNS query name is required.');
            }

            $outcome = $this->transport->query(
                $monitor->target,
                $name,
                $type,
                $monitor->timeout_seconds,
                $monitor->ip_family,
            );
        } catch (InvalidArgumentException|Throwable $exception) {
            $outcome = DnsOutcome::failed(null, $exception->getMessage());
        }

        $body = $outcome->body();
        $context = new CheckContext(
            responseTimeMs: $outcome->latencyMs,
            ip: $outcome->ip,
            connected: $outcome->connected,
            body: $body,
            rawBody: $body,
            dnsRcode: $outcome->rcode,
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
            responseBody: $body,
        );
    }
}
