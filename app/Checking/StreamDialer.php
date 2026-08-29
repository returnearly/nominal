<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;

interface StreamDialer
{
    /**
     * Open a TCP (and optionally TLS) connection, tunneling through an HTTP or SOCKS proxy when given.
     *
     * @param  array<string, mixed>  $ssl
     * @return resource
     */
    public function connect(
        string $host,
        int $port,
        int $timeoutSeconds,
        IpFamily $family,
        ?string $proxyUrl = null,
        array $ssl = [],
    ): mixed;
}
