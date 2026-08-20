<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\DnsQueryType;
use App\Enums\IpFamily;

interface DnsTransport
{
    public function query(
        string $resolver,
        string $name,
        DnsQueryType $type,
        int $timeoutSeconds,
        IpFamily $family,
    ): DnsOutcome;
}
