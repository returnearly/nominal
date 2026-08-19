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
];
