<?php

declare(strict_types=1);

namespace App\Checking;

interface WhoisTransport
{
    public function query(string $server, string $query, int $timeoutSeconds): string;
}
