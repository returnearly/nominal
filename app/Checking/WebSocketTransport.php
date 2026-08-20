<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;

interface WebSocketTransport
{
    /**
     * @param  array<string, string>  $headers
     */
    public function connect(
        string $host,
        int $port,
        string $path,
        bool $secure,
        int $timeoutSeconds,
        IpFamily $family,
        bool $verifyTls,
        array $headers,
        ?string $body = null,
    ): SocketOutcome;
}
