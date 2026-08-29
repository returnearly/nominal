<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\MonitorType;
use InvalidArgumentException;

final readonly class DatabaseUrl
{
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public MonitorType $type,
        public string $scheme,
        public string $host,
        public int $port,
        public ?string $user,
        public ?string $password,
        public ?string $database,
        public array $options = [],
    ) {}

    public static function parse(string $target, MonitorType $type): self
    {
        if (! $type->usesDatabaseUrl()) {
            throw new InvalidArgumentException("Monitor type [{$type->value}] does not use a database URL.");
        }

        $target = trim($target);

        if ($target === '') {
            throw new InvalidArgumentException(self::example($type));
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $target) !== 1) {
            throw new InvalidArgumentException(self::example($type));
        }

        $parts = parse_url($target);

        if (! is_array($parts) || ! isset($parts['host']) || $parts['host'] === '') {
            throw new InvalidArgumentException("Invalid target [{$target}].");
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, self::schemes($type), true)) {
            throw new InvalidArgumentException(self::example($type));
        }

        $host = $parts['host'];

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $database = self::database($parts['path'] ?? null);
        $options = self::options($parts['query'] ?? null);
        $user = self::nullable(isset($parts['user']) ? rawurldecode((string) $parts['user']) : null);
        $password = array_key_exists('pass', $parts)
            ? self::nullable(rawurldecode((string) $parts['pass']))
            : null;

        return new self(
            type: $type,
            scheme: $scheme,
            host: $host,
            port: isset($parts['port']) ? self::port((string) $parts['port']) : self::defaultPort($type, $scheme),
            user: $user,
            password: $password,
            database: $database,
            options: $options,
        );
    }

    public function usesTls(): bool
    {
        if (in_array($this->scheme, ['mysqls', 'mariadbs', 'rediss', 'postgresqls'], true)) {
            return true;
        }

        $mode = strtolower($this->options['sslmode'] ?? $this->options['ssl-mode'] ?? '');

        return in_array($mode, ['require', 'required', 'verify-ca', 'verify-full'], true);
    }

    public function sslMode(bool $verifyTls): string
    {
        $explicit = strtolower($this->options['sslmode'] ?? $this->options['ssl-mode'] ?? '');

        if ($explicit !== '') {
            return $explicit;
        }

        if (! $this->usesTls()) {
            return 'disable';
        }

        return $verifyTls ? 'verify-full' : 'require';
    }

    public function redacted(): string
    {
        $auth = '';

        if ($this->user !== null || $this->password !== null) {
            $auth = $this->user ?? '';

            if ($this->password !== null) {
                $auth .= ':***';
            }

            $auth .= '@';
        }

        $host = $this->isIpv6() ? "[{$this->host}]" : $this->host;
        $path = $this->database !== null ? '/'.$this->database : '';
        $query = $this->options === [] ? '' : '?'.http_build_query($this->options);

        return "{$this->scheme}://{$auth}{$host}:{$this->port}{$path}{$query}";
    }

    public function isIpv6(): bool
    {
        return filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * @return list<string>
     */
    public static function schemes(MonitorType $type): array
    {
        return match ($type) {
            MonitorType::Mysql => ['mysql', 'mysqls', 'mariadb', 'mariadbs'],
            MonitorType::Redis => ['redis', 'rediss'],
            MonitorType::Postgres => ['postgres', 'postgresql', 'pgsql', 'postgresqls'],
            default => [],
        };
    }

    public static function defaultPort(MonitorType $type, ?string $scheme = null): int
    {
        return match ($type) {
            MonitorType::Mysql => 3306,
            MonitorType::Redis => $scheme === 'rediss' ? 6380 : 6379,
            MonitorType::Postgres => 5432,
            default => throw new InvalidArgumentException("Monitor type [{$type->value}] does not use a database URL."),
        };
    }

    public static function example(MonitorType $type): string
    {
        return match ($type) {
            MonitorType::Mysql => 'MySQL targets must be a URL such as mysql://user:pass@db.example.com:3306/app.',
            MonitorType::Redis => 'Redis targets must be a URL such as redis://:pass@cache.example.com:6379/0.',
            MonitorType::Postgres => 'PostgreSQL targets must be a URL such as postgres://user:pass@db.example.com:5432/app.',
            default => 'Target must be a database connection URL.',
        };
    }

    private static function database(?string $path): ?string
    {
        if ($path === null || $path === '' || $path === '/') {
            return null;
        }

        $database = rawurldecode(ltrim($path, '/'));

        return $database === '' ? null : $database;
    }

    /**
     * @return array<string, string>
     */
    private static function options(?string $query): array
    {
        if ($query === null || $query === '') {
            return [];
        }

        parse_str($query, $options);

        $normalized = [];

        foreach ($options as $key => $value) {
            if (! is_string($key) || $key === '' || is_array($value)) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
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
}
