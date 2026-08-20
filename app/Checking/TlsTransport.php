<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;

interface TlsTransport
{
    public function connect(
        string $host,
        int $port,
        int $timeoutSeconds,
        IpFamily $family,
        bool $verifyTls,
        ?string $body = null,
    ): SocketOutcome;
}
