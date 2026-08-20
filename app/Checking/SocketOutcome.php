<?php

declare(strict_types=1);

namespace App\Checking;

final readonly class SocketOutcome
{
    public function __construct(
        public bool $connected,
        public ?int $latencyMs,
        public ?string $ip,
        public ?string $message,
        public ?string $body = null,
    ) {}

    public static function ok(int $latencyMs, ?string $ip, ?string $body = null): self
    {
        return new self(true, $latencyMs, $ip, null, $body);
    }

    public static function failed(?int $latencyMs, string $message, ?string $ip = null): self
    {
        return new self(false, $latencyMs, $ip, $message);
    }
}
