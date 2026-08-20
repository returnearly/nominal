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

    public function getLabel(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::Ping => 'Ping',
            self::Tcp => 'TCP',
            self::Dns => 'DNS',
            self::Tls => 'TLS',
        };
    }

    public function usesHttpRequest(): bool
    {
        return $this === self::Http;
    }

    public function usesRequestBody(): bool
    {
        return match ($this) {
            self::Http, self::Tcp, self::Tls => true,
            default => false,
        };
    }

    public function usesVerifyTls(): bool
    {
        return match ($this) {
            self::Http, self::Tls => true,
            default => false,
        };
    }

    public function usesDnsQuery(): bool
    {
        return $this === self::Dns;
    }
}
