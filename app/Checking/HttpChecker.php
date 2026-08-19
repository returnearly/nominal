<?php

declare(strict_types=1);

namespace App\Checking;

use App\Conditions\CheckContext;
use App\Enums\IpFamily;
use App\Models\Monitor;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Throwable;

final class HttpChecker
{
    private ?Client $client = null;

    public function __construct(
        private readonly ConditionRunner $conditions,
        private readonly TlsCertificateReader $certificates,
    ) {}

    public function withClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function check(Monitor $monitor): ProbeResult
    {
        $ip = null;
        $status = null;
        $body = null;
        $rawBody = null;
        $connected = false;
        $latencyMs = null;
        $error = null;
        $started = hrtime(true);

        try {
            $response = $this->client()->request(
                $monitor->method?->value ?? 'GET',
                $monitor->target,
                $this->options($monitor, $ip),
            );
            $connected = true;
            $status = $response->getStatusCode();
            $rawBody = (string) $response->getBody();
            $body = $this->decodeBody($rawBody);
        } catch (GuzzleException $exception) {
            $error = $exception->getMessage();
            $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $latencyMs ??= (int) ((hrtime(true) - $started) / 1_000_000);
        }

        $latencyMs ??= (int) ((hrtime(true) - $started) / 1_000_000);

        $certificateExpiresAt = $this->certificateExpiry($monitor);
        $certificateSeconds = $certificateExpiresAt instanceof DateTimeImmutable
            ? $certificateExpiresAt->getTimestamp() - time()
            : null;

        $context = new CheckContext(
            status: $status,
            responseTimeMs: $latencyMs,
            ip: $ip,
            connected: $connected,
            certificateExpirationSeconds: $certificateSeconds,
            body: $body,
            rawBody: $rawBody,
        );

        [$outcomes, $success, $message] = $this->conditions->run($monitor, $context, $error);

        return new ProbeResult(
            success: $success,
            connected: $connected,
            latencyMs: $latencyMs,
            httpStatus: $status,
            resolvedIp: $ip,
            certificateExpiresAt: $certificateExpiresAt,
            message: $message,
            conditionResults: $outcomes,
            responseBody: $rawBody,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Monitor $monitor, ?string &$ip): array
    {
        $curlResolve = match ($monitor->ip_family) {
            IpFamily::Ipv4 => CURL_IPRESOLVE_V4,
            IpFamily::Ipv6 => CURL_IPRESOLVE_V6,
            IpFamily::Any => CURL_IPRESOLVE_WHATEVER,
        };

        $options = [
            RequestOptions::TIMEOUT => $monitor->timeout_seconds,
            RequestOptions::CONNECT_TIMEOUT => $monitor->timeout_seconds,
            RequestOptions::ALLOW_REDIRECTS => $monitor->follow_redirects,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::VERIFY => $monitor->verify_tls,
            RequestOptions::CURL => [
                CURLOPT_IPRESOLVE => $curlResolve,
            ],
            RequestOptions::ON_STATS => function (TransferStats $stats) use (&$ip): void {
                $primaryIp = $stats->getHandlerStat('primary_ip');
                if (is_string($primaryIp) && $primaryIp !== '') {
                    $ip = $primaryIp;
                }
            },
        ];

        $headers = $monitor->requestHeadersArray();

        if ($headers !== []) {
            $options[RequestOptions::HEADERS] = $headers;
        }

        if ($monitor->request_body !== null && $monitor->request_body !== '') {
            $options[RequestOptions::BODY] = $monitor->request_body;
        }

        return $options;
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

    private function certificateExpiry(Monitor $monitor): ?DateTimeImmutable
    {
        $parts = parse_url($monitor->target);

        if (($parts['scheme'] ?? null) !== 'https') {
            return null;
        }

        $host = $parts['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = (int) ($parts['port'] ?? 443);

        return $this->certificates->expiresAt($host, $port, $monitor->timeout_seconds);
    }

    private function client(): Client
    {
        return $this->client ?? new Client;
    }
}
