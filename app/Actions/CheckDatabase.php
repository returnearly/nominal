<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\DatabaseUrl;
use App\Checking\MysqlTransport;
use App\Checking\PostgresTransport;
use App\Checking\ProbeResult;
use App\Checking\RedisTransport;
use App\Checking\SocketOutcome;
use App\Conditions\CheckContext;
use App\Enums\MonitorType;
use App\Models\Monitor;
use InvalidArgumentException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use Throwable;

final readonly class CheckDatabase implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateCheckConditions $conditions,
        private MysqlTransport $mysql,
        private RedisTransport $redis,
        private PostgresTransport $postgres,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
    {
        try {
            $url = DatabaseUrl::parse($monitor->target, $monitor->type);
            $outcome = $this->transport($monitor)->connect(
                $url,
                $monitor->timeout_seconds,
                $monitor->ip_family,
                $monitor->verify_tls,
                $this->command($monitor),
                $monitor->outboundProxyUrl(),
            );
        } catch (InvalidArgumentException|Throwable $exception) {
            $outcome = SocketOutcome::failed(null, $exception->getMessage());
        }

        $context = new CheckContext(
            responseTimeMs: $outcome->latencyMs,
            ip: $outcome->ip,
            connected: $outcome->connected,
            body: $this->decodeBody($outcome->body),
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
            certificateExpiresAt: null,
            message: $message,
            conditionResults: $outcomes,
            responseBody: $outcome->body,
        );
    }

    private function transport(Monitor $monitor): MysqlTransport|RedisTransport|PostgresTransport
    {
        return match ($monitor->type) {
            MonitorType::Mysql => $this->mysql,
            MonitorType::Redis => $this->redis,
            MonitorType::Postgres => $this->postgres,
            default => throw new InvalidArgumentException("Monitor type [{$monitor->type->value}] is not a database check."),
        };
    }

    private function command(Monitor $monitor): ?string
    {
        if ($monitor->request_body === null) {
            return null;
        }

        $command = trim($monitor->request_body);

        return $command === '' ? null : $command;
    }

    private function decodeBody(?string $rawBody): mixed
    {
        if ($rawBody === null || $rawBody === '') {
            return null;
        }

        try {
            return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $rawBody;
        }
    }
}
