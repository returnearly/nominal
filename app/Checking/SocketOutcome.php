<?php

declare(strict_types=1);

namespace App\Checking;

use DateTimeImmutable;

final readonly class SocketOutcome
{
    public function __construct(
        public bool $connected,
        public ?int $latencyMs,
        public ?string $ip,
        public ?string $message,
        public ?string $body = null,
        public ?DateTimeImmutable $certificateExpiresAt = null,
    ) {}

    public static function ok(
        int $latencyMs,
        ?string $ip,
        ?string $body = null,
        ?DateTimeImmutable $certificateExpiresAt = null,
    ): self {
        return new self(true, $latencyMs, $ip, null, $body, $certificateExpiresAt);
    }

    public static function failed(?int $latencyMs, string $message, ?string $ip = null): self
    {
        return new self(false, $latencyMs, $ip, $message);
    }
}
