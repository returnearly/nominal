<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final readonly class ProxyUrl
{
    /**
     * @param  'http'|'https'|'socks4'|'socks4a'|'socks5'|'socks5h'  $scheme
     */
    public function __construct(
        public string $scheme,
        public string $host,
        public int $port,
        public ?string $username = null,
        public ?string $password = null,
    ) {}

    public static function parse(string $url): self
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException('Proxy URL cannot be empty.');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host']) || $parts['host'] === '') {
            throw new InvalidArgumentException("Invalid proxy URL [{$url}].");
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $scheme = match ($scheme) {
            'http', 'https', 'socks4', 'socks4a', 'socks5', 'socks5h' => $scheme,
            'socks' => 'socks5',
            default => throw new InvalidArgumentException(
                "Unsupported proxy scheme [{$scheme}]. Use http, https, socks4, socks5, or socks5h.",
            ),
        };

        $host = $parts['host'];

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : self::defaultPort($scheme);

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Invalid proxy port [{$port}].");
        }

        $username = isset($parts['user']) ? rawurldecode((string) $parts['user']) : null;
        $password = isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null;

        return new self($scheme, $host, $port, $username, $password);
    }

    public static function tryParse(?string $url): ?self
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        return self::parse($url);
    }

    /**
     * Guzzle `proxy` option from environment / config. Used for HTTP checks and webhooks.
     *
     * @return string|array{http?: string, https?: string, no?: list<string>}|null
     */
    public static function guzzleFromConfig(): string|array|null
    {
        $explicit = self::configured('url');
        $http = self::configured('http');
        $https = self::configured('https');
        $all = self::configured('all');
        $no = self::noProxyList();

        if ($explicit !== null) {
            return $no === [] ? $explicit : [
                'http' => $explicit,
                'https' => $explicit,
                'no' => $no,
            ];
        }

        if ($http === null && $https === null && $all === null) {
            return null;
        }

        if ($http === null && $https === null) {
            return $no === [] ? $all : [
                'http' => $all,
                'https' => $all,
                'no' => $no,
            ];
        }

        $config = [
            'http' => $http ?? $all,
            'https' => $https ?? $http ?? $all,
        ];

        if ($no !== []) {
            $config['no'] = $no;
        }

        return $config;
    }

    public function isSocks(): bool
    {
        return str_starts_with($this->scheme, 'socks');
    }

    public function isHttp(): bool
    {
        return $this->scheme === 'http' || $this->scheme === 'https';
    }

    public function remoteDns(): bool
    {
        return $this->scheme === 'socks5h' || $this->scheme === 'socks4a';
    }

    public function hostWithPort(): string
    {
        $host = filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? "[{$this->host}]"
            : $this->host;

        return "{$host}:{$this->port}";
    }

    public function basicAuthorization(): ?string
    {
        if ($this->username === null) {
            return null;
        }

        return 'Basic '.base64_encode($this->username.':'.($this->password ?? ''));
    }

    private static function defaultPort(string $scheme): int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => 1080,
        };
    }

    private static function configured(string $key): ?string
    {
        $value = config("nominal.proxy.{$key}");

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function noProxyList(): array
    {
        $value = self::configured('no');

        if ($value === null) {
            return [];
        }

        $hosts = [];

        foreach (explode(',', $value) as $host) {
            $host = trim($host);

            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }
}
