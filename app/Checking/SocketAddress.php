<?php

declare(strict_types=1);

namespace App\Checking;

use InvalidArgumentException;

final readonly class SocketAddress
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $scheme = null,
        public string $path = '',
    ) {}

    public static function parse(string $target, ?int $defaultPort = null): self
    {
        $target = trim($target);

        if ($target === '') {
            throw new InvalidArgumentException('Target cannot be empty.');
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $target) === 1) {
            return self::fromUrl($target, $defaultPort);
        }

        if (str_starts_with($target, '[')) {
            return self::fromBracketed($target, $defaultPort);
        }

        if (substr_count($target, ':') > 1) {
            throw new InvalidArgumentException('IPv6 targets must be wrapped in brackets, e.g. [2001:db8::1]:5432.');
        }

        if (str_contains($target, ':')) {
            [$host, $port] = explode(':', $target, 2);

            if ($host === '') {
                throw new InvalidArgumentException('Target host cannot be empty.');
            }

            return new self(host: $host, port: self::port($port));
        }

        return new self(host: $target, port: self::requireDefaultPort($defaultPort));
    }

    public function remote(string $scheme): string
    {
        $host = $this->isIpv6() ? "[{$this->host}]" : $this->host;

        return "{$scheme}://{$host}:{$this->port}";
    }

    public function hostWithPort(): string
    {
        return $this->isIpv6() ? "[{$this->host}]:{$this->port}" : "{$this->host}:{$this->port}";
    }

    public function isIpv6(): bool
    {
        return filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    private static function fromUrl(string $target, ?int $defaultPort): self
    {
        $parts = parse_url($target);

        if (! is_array($parts) || ! isset($parts['host']) || $parts['host'] === '') {
            throw new InvalidArgumentException("Invalid target [{$target}].");
        }

        $port = isset($parts['port']) ? self::port((string) $parts['port']) : self::requireDefaultPort($defaultPort);
        $path = $parts['path'] ?? '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        $host = $parts['host'];

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return new self(
            host: $host,
            port: $port,
            scheme: isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : null,
            path: $path === '' ? '' : $path,
        );
    }

    private static function fromBracketed(string $target, ?int $defaultPort): self
    {
        if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $target, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid target [{$target}].");
        }

        $port = isset($matches[2]) && $matches[2] !== ''
            ? self::port($matches[2])
            : self::requireDefaultPort($defaultPort);

        return new self(host: $matches[1], port: $port);
    }

    private static function port(string $port): int
    {
        if (preg_match('/^\d+$/', $port) !== 1) {
            throw new InvalidArgumentException("Invalid port [{$port}].");
        }

        $value = (int) $port;

        if ($value < 1 || $value > 65535) {
            throw new InvalidArgumentException("Invalid port [{$port}].");
        }

        return $value;
    }

    private static function requireDefaultPort(?int $defaultPort): int
    {
        if ($defaultPort === null) {
            throw new InvalidArgumentException('Target must include a port, e.g. host:5432.');
        }

        return self::port((string) $defaultPort);
    }
}
