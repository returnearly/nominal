<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Checking\SocketAddress;
use App\Checking\SocketOutcome;
use App\Checking\WebSocketTransport;
use App\Conditions\CheckContext;
use App\Models\Monitor;
use InvalidArgumentException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use Throwable;

final readonly class CheckWebSocket implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateCheckConditions $conditions,
        private WebSocketTransport $transport,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
    {
        try {
            $secure = $this->isSecure($monitor->target);
            $address = SocketAddress::parse($monitor->target, $secure ? 443 : 80);
            $outcome = $this->transport->connect(
                $address->host,
                $address->port,
                $address->path === '' ? '/' : $address->path,
                $secure,
                $monitor->timeout_seconds,
                $monitor->ip_family,
                $monitor->verify_tls,
                $monitor->outboundRequestHeaders(),
                $monitor->request_body,
                $monitor->outboundProxyUrl(),
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

    private function isSecure(string $target): bool
    {
        $scheme = strtolower((string) (parse_url($target, PHP_URL_SCHEME) ?: ''));

        return in_array($scheme, ['wss', 'https', 'ssl'], true);
    }
}
