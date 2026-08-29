<?php

declare(strict_types=1);

use App\Enums\InterfaceAuth;

return [
    'probe_region' => env('PROBE_REGION', 'local'),
    'metrics_prefix' => 'nominal_',

    /*
    |--------------------------------------------------------------------------
    | Interface authentication
    |--------------------------------------------------------------------------
    |
    | How humans reach the Filament admin (`/admin`). GraphQL stays Sanctum.
    |
    | none       — no login page; visitors are signed in as a local operator
    | login      — email and password (default)
    | cloudflare — Cloudflare Access SSO via returnearly/laravel-cloudflare-zero-trust
    |
    */
    'interface_auth' => env('INTERFACE_AUTH', InterfaceAuth::Login->value),

    'anonymous_operator' => [
        'email' => env('INTERFACE_OPERATOR_EMAIL', 'operator@nominal.local'),
        'name' => env('INTERFACE_OPERATOR_NAME', 'Operator'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound proxy
    |--------------------------------------------------------------------------
    |
    | Used for HTTP checks (when a monitor has no proxy_url) and notification
    | webhooks. SOCKS URLs belong in ALL_PROXY / NOMINAL_PROXY_URL, e.g.
    | socks5h://127.0.0.1:1080. Per-monitor proxy_url overrides this for
    | HTTP, TCP, TLS, WebSocket, and Redis checks.
    |
    */
    'proxy' => [
        'url' => env('NOMINAL_PROXY_URL'),
        'http' => env('HTTP_PROXY', env('http_proxy')),
        'https' => env('HTTPS_PROXY', env('https_proxy')),
        'all' => env('ALL_PROXY', env('all_proxy', env('SOCKS_PROXY', env('socks_proxy')))),
        'no' => env('NO_PROXY', env('no_proxy')),
    ],
];
