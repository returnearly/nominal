<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy addresses, or "*" when the app sits behind a
    | reverse proxy that sets X-Forwarded-* (Railway's public proxy does).
    | Empty leaves forwarded headers untrusted.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
