<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MonitorType: string implements HasLabel
{
    case Http = 'http';
    case Ping = 'ping';
    case Tcp = 'tcp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::Ping => 'Ping',
            self::Tcp => 'TCP',
        };
    }

    public function usesHttpRequest(): bool
    {
        return $this === self::Http;
    }

    public function usesRequestBody(): bool
    {
        return match ($this) {
            self::Http, self::Tcp => true,
            default => false,
        };
    }
}
