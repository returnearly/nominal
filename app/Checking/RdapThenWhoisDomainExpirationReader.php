<?php

declare(strict_types=1);

namespace App\Checking;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class RdapThenWhoisDomainExpirationReader implements DomainExpirationReader
{
    private const BootstrapUrl = 'https://data.iana.org/rdap/dns.json';

    private const RefreshWithinSeconds = 86400;

    private const LongTtlSeconds = 864000;

    private const ShortTtlSeconds = 259200;

    private const LongRemainingSeconds = 2592000;

    public function __construct(
        private WhoisClient $whois,
        private Client $http,
    ) {}

    public function expiresAt(string $hostname, int $timeoutSeconds = 10): ?DateTimeImmutable
    {
        $hostname = DomainHostname::fromTarget($hostname);

        if ($hostname === null) {
            return null;
        }

        $cached = $this->cached($hostname);

        if ($cached !== null && ! $this->shouldRefresh($cached)) {
            return $cached['expires_at'];
        }

        $expiresAt = $this->rdap($hostname, $timeoutSeconds)
            ?? $this->whois->expirationDate($hostname, $timeoutSeconds)
            ?? $cached['expires_at'] ?? null;

        if ($expiresAt instanceof DateTimeImmutable) {
            $this->remember($hostname, $expiresAt);
        }

        return $expiresAt;
    }

    /**
     * @return array{expires_at: DateTimeImmutable, cached_until: DateTimeImmutable}|null
     */
    private function cached(string $hostname): ?array
    {
        $payload = Cache::get($this->cacheKey($hostname));

        if (! is_array($payload) || ! isset($payload['expires_at'], $payload['cached_until'])) {
            return null;
        }

        try {
            return [
                'expires_at' => new DateTimeImmutable((string) $payload['expires_at']),
                'cached_until' => new DateTimeImmutable((string) $payload['cached_until']),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array{expires_at: DateTimeImmutable, cached_until: DateTimeImmutable}  $cached
     */
    private function shouldRefresh(array $cached): bool
    {
        $now = time();

        return ($cached['cached_until']->getTimestamp() - $now) <= self::RefreshWithinSeconds
            || ($cached['expires_at']->getTimestamp() - $now) <= self::RefreshWithinSeconds;
    }

    private function remember(string $hostname, DateTimeImmutable $expiresAt): void
    {
        $remaining = $expiresAt->getTimestamp() - time();
        $ttl = $remaining > self::LongRemainingSeconds
            ? self::LongTtlSeconds
            : self::ShortTtlSeconds;

        Cache::put($this->cacheKey($hostname), [
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
            'cached_until' => now()->addSeconds($ttl)->toIso8601String(),
        ], $ttl);
    }

    private function cacheKey(string $hostname): string
    {
        return 'nominal:domain-expiration:'.$hostname;
    }

    private function rdap(string $hostname, int $timeoutSeconds): ?DateTimeImmutable
    {
        $base = $this->rdapBase($hostname, $timeoutSeconds);

        if ($base === null) {
            return null;
        }

        $payload = $this->json(rtrim($base, '/').'/domain/'.$hostname, $timeoutSeconds);

        if (! is_array($payload) || ! isset($payload['events']) || ! is_array($payload['events'])) {
            return null;
        }

        foreach ($payload['events'] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $action = strtolower((string) ($event['eventAction'] ?? ''));
            $date = $event['eventDate'] ?? null;

            if ($action !== 'expiration' || ! is_string($date) || $date === '') {
                continue;
            }

            try {
                $expiresAt = new DateTimeImmutable($date);
            } catch (Throwable) {
                continue;
            }

            if ($expiresAt->getTimestamp() > 0) {
                return $expiresAt;
            }
        }

        return null;
    }

    private function rdapBase(string $hostname, int $timeoutSeconds): ?string
    {
        $bootstrap = $this->bootstrap($timeoutSeconds);

        if ($bootstrap === []) {
            return null;
        }

        $labels = explode('.', $hostname);
        $match = null;
        $matchLength = 0;

        foreach ($bootstrap as $tld => $urls) {
            $length = substr_count($tld, '.') + 1;

            if ($length <= $matchLength) {
                continue;
            }

            $suffix = implode('.', array_slice($labels, -$length));

            if (strcasecmp($suffix, $tld) === 0) {
                $match = $urls[0] ?? null;
                $matchLength = $length;
            }
        }

        return is_string($match) && $match !== '' ? $match : null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function bootstrap(int $timeoutSeconds): array
    {
        /** @var array<string, list<string>>|null $cached */
        $cached = Cache::get('nominal:rdap:bootstrap');

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $payload = $this->json(self::BootstrapUrl, $timeoutSeconds);
        $services = is_array($payload) && isset($payload['services']) && is_array($payload['services'])
            ? $payload['services']
            : [];

        $map = [];

        foreach ($services as $service) {
            if (! is_array($service) || count($service) < 2 || ! is_array($service[0]) || ! is_array($service[1])) {
                continue;
            }

            $urls = array_values(array_filter(
                $service[1],
                fn (mixed $url): bool => is_string($url) && $url !== '',
            ));

            if ($urls === []) {
                continue;
            }

            foreach ($service[0] as $tld) {
                if (is_string($tld) && $tld !== '') {
                    $map[strtolower($tld)] = $urls;
                }
            }
        }

        if ($map !== []) {
            Cache::put('nominal:rdap:bootstrap', $map, now()->addDay());
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function json(string $url, int $timeoutSeconds): ?array
    {
        try {
            $response = $this->http->get($url, [
                RequestOptions::TIMEOUT => $timeoutSeconds,
                RequestOptions::CONNECT_TIMEOUT => $timeoutSeconds,
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/rdap+json, application/json',
                    'User-Agent' => 'Nominal/1.0',
                ],
            ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        try {
            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
