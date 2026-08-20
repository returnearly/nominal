<?php

declare(strict_types=1);

namespace App\Checking;

use DateTimeImmutable;

interface TlsCertificateReader
{
    public function expiresAt(string $host, int $port, int $timeoutSeconds, ?string $proxyUrl = null): ?DateTimeImmutable;
}
