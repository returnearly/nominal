<?php

declare(strict_types=1);

namespace App\Checking;

use DateTimeImmutable;

interface DomainExpirationReader
{
    public function expiresAt(string $hostname, int $timeoutSeconds = 10): ?DateTimeImmutable;
}
