<?php

declare(strict_types=1);

namespace App\Enums;

enum InterfaceAuth: string
{
    case None = 'none';
    case Login = 'login';
    case Cloudflare = 'cloudflare';

    public static function current(): self
    {
        return self::tryFrom((string) config('nominal.interface_auth')) ?? self::Login;
    }
}
