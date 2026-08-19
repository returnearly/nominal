<?php

declare(strict_types=1);

namespace App\Checking;

use App\Conditions\ConditionOutcome;
use DateTimeImmutable;

final readonly class ProbeResult
{
    /**
     * @param  list<ConditionOutcome>  $conditionResults
     */
    public function __construct(
        public bool $success,
        public bool $connected,
        public ?int $latencyMs,
        public ?int $httpStatus,
        public ?string $resolvedIp,
        public ?DateTimeImmutable $certificateExpiresAt,
        public ?string $message,
        public array $conditionResults,
        public ?string $responseBody = null,
    ) {}
}
