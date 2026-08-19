<?php

declare(strict_types=1);

namespace App\Conditions;

final readonly class CheckContext
{
    public function __construct(
        public ?int $status = null,
        public ?int $responseTimeMs = null,
        public ?string $ip = null,
        public bool $connected = false,
        public ?int $certificateExpirationSeconds = null,
        public mixed $body = null,
        public ?string $rawBody = null,
        public bool $bodyPathExisted = true,
    ) {}
}
