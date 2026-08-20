<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MonitorType: string implements HasLabel
{
    case Http = 'http';
    case Ping = 'ping';
    case Tcp = 'tcp';
    case Dns = 'dns';
    case Tls = 'tls';
    case Heartbeat = 'heartbeat';
    case Udp = 'udp';
    case WebSocket = 'websocket';

    public function getLabel(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::Ping => 'Ping',
            self::Tcp => 'TCP',
            self::Dns => 'DNS',
            self::Tls => 'TLS',
            self::Heartbeat => 'Heartbeat',
            self::Udp => 'UDP',
            self::WebSocket => 'WebSocket',
        };
    }

    public function usesHttpRequest(): bool
    {
        return $this === self::Http;
    }

    public function usesRequestBody(): bool
    {
        return match ($this) {
            self::Http, self::Tcp, self::Tls, self::Udp, self::WebSocket => true,
            default => false,
        };
    }

    public function usesVerifyTls(): bool
    {
        return match ($this) {
            self::Http, self::Tls, self::WebSocket => true,
            default => false,
        };
    }

    public function usesRequestHeaders(): bool
    {
        return match ($this) {
            self::Http, self::WebSocket => true,
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
}
