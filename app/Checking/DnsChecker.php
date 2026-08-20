<?php

declare(strict_types=1);

namespace App\Checking;

use App\Conditions\CheckContext;
use App\Enums\DnsQueryType;
use App\Models\Monitor;
use InvalidArgumentException;
use Throwable;

final class DnsChecker
{
    public function __construct(
        private readonly ConditionRunner $conditions,
        private readonly DnsTransport $transport,
    ) {}

    public function check(Monitor $monitor): ProbeResult
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
            responseBody: $body,
        );
    }
}
