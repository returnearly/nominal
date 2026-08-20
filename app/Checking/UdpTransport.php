<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;

interface UdpTransport
{
    public function connect(
        string $host,
        int $port,
        int $timeoutSeconds,
        IpFamily $family,
        ?string $body = null,
    ): SocketOutcome;
}
