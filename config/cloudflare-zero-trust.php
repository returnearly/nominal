<?php

declare(strict_types=1);

use App\Enums\InterfaceAuth;
use ReturnEarly\CloudflareZeroTrust\Enums\PrincipalKind;

$adminAudience = env('CLOUDFLARE_ADMIN_AUD');

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Cloudflare Access Validation
    |--------------------------------------------------------------------------
    |
    | Defaults to on when INTERFACE_AUTH=cloudflare. There is no environment-name
    | bypass — set CLOUDFLARE_ZERO_TRUST_ENABLED=false to skip JWT checks.
    |
    */
    'enabled' => (bool) env(
        'CLOUDFLARE_ZERO_TRUST_ENABLED',
        env('INTERFACE_AUTH', InterfaceAuth::Login->value) === InterfaceAuth::Cloudflare->value,
    ),

    'header' => 'Cf-Access-Jwt-Assertion',

    'guard' => env('CLOUDFLARE_ZERO_TRUST_GUARD', 'cloudflare'),

    'clock_leeway' => (int) env('CLOUDFLARE_ZERO_TRUST_CLOCK_LEEWAY', 30),

    'jwks' => [
        'cache_store' => env('CLOUDFLARE_ZERO_TRUST_CACHE_STORE'),
        'cache_ttl' => (int) env('CLOUDFLARE_ZERO_TRUST_JWKS_CACHE_TTL', 3600),
        'connect_timeout' => (float) env('CLOUDFLARE_ZERO_TRUST_JWKS_CONNECT_TIMEOUT', 2),
        'timeout' => (float) env('CLOUDFLARE_ZERO_TRUST_JWKS_TIMEOUT', 5),
        'refresh_rate_limit' => (int) env('CLOUDFLARE_ZERO_TRUST_JWKS_REFRESH_RATE_LIMIT', 10),
        'refresh_rate_window' => (int) env('CLOUDFLARE_ZERO_TRUST_JWKS_REFRESH_RATE_WINDOW', 60),
    ],

    'identity' => [
        'enabled' => (bool) env('CLOUDFLARE_ZERO_TRUST_IDENTITY_ENABLED', true),
        'connect_timeout' => (float) env('CLOUDFLARE_ZERO_TRUST_IDENTITY_CONNECT_TIMEOUT', 2),
        'timeout' => (float) env('CLOUDFLARE_ZERO_TRUST_IDENTITY_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Accounts and Applications
    |--------------------------------------------------------------------------
    |
    | The Filament admin is the `admin` application. CLOUDFLARE_ADMIN_AUD must
    | be set before INTERFACE_AUTH=cloudflare will authenticate anyone.
    |
    */
    'accounts' => filled($adminAudience) ? [
        'returnearly' => [
            'team_domain' => env('CLOUDFLARE_TEAM_DOMAIN', 'https://returnearly.cloudflareaccess.com'),
            'applications' => [
                'admin' => [
                    'audience' => [$adminAudience],
                    'enabled' => (bool) env('CLOUDFLARE_ADMIN_ENABLED', true),
                    'principals' => [PrincipalKind::User],
                ],
            ],
        ],
    ] : [],

];
