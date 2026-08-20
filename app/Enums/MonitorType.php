<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MonitorType: string implements HasLabel
{
    case Http = 'http';
    case GraphQL = 'graphql';
    case Ping = 'ping';
    case Tcp = 'tcp';
    case Dns = 'dns';
    case Tls = 'tls';
    case Heartbeat = 'heartbeat';
    case Udp = 'udp';
    case WebSocket = 'websocket';
    case Mysql = 'mysql';
    case Redis = 'redis';
    case Postgres = 'postgres';

    public function getLabel(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::GraphQL => 'GraphQL',
            self::Ping => 'Ping',
            self::Tcp => 'TCP',
            self::Dns => 'DNS',
            self::Tls => 'TLS',
            self::Heartbeat => 'Heartbeat',
            self::Udp => 'UDP',
            self::WebSocket => 'WebSocket',
            self::Mysql => 'MySQL',
            self::Redis => 'Redis',
            self::Postgres => 'PostgreSQL',
        };
    }

    public function usesHttpRequest(): bool
    {
        return match ($this) {
            self::Http, self::GraphQL => true,
            default => false,
        };
    }

    public function wrapsGraphQLBody(): bool
    {
        return $this === self::GraphQL;
    }

    public function usesRequestBody(): bool
    {
        return match ($this) {
            self::Http, self::GraphQL, self::Tcp, self::Tls, self::Udp, self::WebSocket, self::Mysql, self::Redis, self::Postgres => true,
            default => false,
        };
    }

    public function usesVerifyTls(): bool
    {
        return match ($this) {
            self::Http, self::GraphQL, self::Tls, self::WebSocket, self::Mysql, self::Redis, self::Postgres => true,
            default => false,
        };
    }

    public function usesProxy(): bool
    {
        return match ($this) {
            self::Http, self::GraphQL, self::Tcp, self::Tls, self::WebSocket, self::Redis => true,
            default => false,
        };
    }

    public function usesDatabaseUrl(): bool
    {
        return match ($this) {
            self::Mysql, self::Redis, self::Postgres => true,
            default => false,
        };
    }

    public function usesRequestHeaders(): bool
    {
        return match ($this) {
            self::Http, self::GraphQL, self::WebSocket => true,
            default => false,
        };
    }

    public function usesDnsQuery(): bool
    {
        return $this === self::Dns;
    }

    public function isHeartbeat(): bool
    {
        return $this === self::Heartbeat;
    }

    public function usesOutboundProbe(): bool
    {
        return ! $this->isHeartbeat();
    }

    public function supportsDomainExpiration(): bool
    {
        return match ($this) {
            self::Dns, self::Heartbeat => false,
            default => true,
        };
    }
}
