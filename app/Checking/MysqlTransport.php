<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;

interface MysqlTransport
{
    public function connect(
        DatabaseUrl $url,
        int $timeoutSeconds,
        IpFamily $family,
        bool $verifyTls,
        ?string $command = null,
        ?string $proxyUrl = null,
    ): SocketOutcome;
}
