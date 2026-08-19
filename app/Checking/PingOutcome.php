<?php

declare(strict_types=1);

namespace App\Checking;

final readonly class PingOutcome
{
    public function __construct(
        public bool $connected,
        public ?int $latencyMs,
        public ?string $ip,
        public ?string $message,
    ) {}
}
