<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Checking\TlsCertificateReader;
use App\Conditions\CheckContext;
use App\Enums\IpFamily;
use App\Models\Monitor;
use App\Support\ProxyUrl;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use Throwable;

final readonly class CheckHttp implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private EvaluateCheckConditions $conditions,
        private TlsCertificateReader $certificates,
        private Client $client,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
    {
        $ip = null;
        $effectiveUri = null;
        $status = null;
        $body = null;
        $rawBody = null;
        $redirectUrl = null;
        $connected = false;
        $latencyMs = null;
        $error = null;
        $started = hrtime(true);

        try {
            $response = $this->client->request(
                $monitor->method?->value ?? 'GET',
                $monitor->target,
                $this->options($monitor, $ip, $effectiveUri),
            );
            $connected = true;
            $status = $response->getStatusCode();
            $rawBody = (string) $response->getBody();
            $body = $this->decodeBody($rawBody);
            $redirectUrl = $this->redirectUrl($response, $effectiveUri ?? $monitor->target);
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
            redirectUrl: $redirectUrl,
        );

        [$outcomes, $success, $message, $domainExpiresAt] = $this->conditions->handle($monitor, $context, $error);

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
            domainExpiresAt: $domainExpiresAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Monitor $monitor, ?string &$ip, ?string &$effectiveUri): array
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
            RequestOptions::ON_STATS => function (TransferStats $stats) use (&$ip, &$effectiveUri): void {
                $primaryIp = $stats->getHandlerStat('primary_ip');
                if (is_string($primaryIp) && $primaryIp !== '') {
                    $ip = $primaryIp;
                }

                $effectiveUri = (string) $stats->getEffectiveUri();
            },
        ];

        $headers = $this->requestHeaders($monitor);
        $body = $this->requestBody($monitor);

        if ($headers !== []) {
            $options[RequestOptions::HEADERS] = $headers;
        }

        if ($body !== null) {
            $options[RequestOptions::BODY] = $body;
        }

        $proxy = $this->proxy($monitor);

        if ($proxy !== null) {
            $options[RequestOptions::PROXY] = $proxy;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(Monitor $monitor): array
    {
        $headers = $monitor->requestHeadersArray();

        if ($monitor->type->wrapsGraphQLBody() && ! $this->hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    private function requestBody(Monitor $monitor): ?string
    {
        if ($monitor->type->wrapsGraphQLBody()) {
            return json_encode(
                ['query' => (string) $monitor->request_body],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        if ($monitor->request_body === null || $monitor->request_body === '') {
            return null;
        }

        return $monitor->request_body;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $key) {
            if (strcasecmp((string) $key, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string|array<string, mixed>|null
     */
    private function proxy(Monitor $monitor): string|array|null
    {
        $configured = $monitor->outboundProxyUrl();

        if ($configured !== null) {
            return $configured;
        }

        return ProxyUrl::guzzleFromConfig();
    }

    private function redirectUrl(ResponseInterface $response, string $baseUrl): string
    {
        $location = $response->getHeaderLine('Location');

        if ($location !== '') {
            return $this->absoluteUrl($location, $baseUrl);
        }

        return $baseUrl;
    }

    private function absoluteUrl(string $location, string $baseUrl): string
    {
        try {
            return (string) UriResolver::resolve(new Uri($baseUrl), new Uri($location));
        } catch (InvalidArgumentException) {
            return $location;
        }
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

        return $this->certificates->expiresAt($host, $port, $monitor->timeout_seconds, $monitor->outboundProxyUrl());
    }
}
