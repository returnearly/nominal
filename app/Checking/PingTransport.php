<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;

interface PingTransport
{
    public function ping(string $host, int $timeoutSeconds, IpFamily $family): PingOutcome;
}
