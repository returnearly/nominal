<?php

declare(strict_types=1);

namespace App\Checking;

final readonly class DnsOutcome
{
    /**
     * @param  list<string>  $answers
     */
    public function __construct(
        public bool $connected,
        public ?int $latencyMs,
        public ?string $ip,
        public ?string $rcode,
        public array $answers,
        public ?string $message,
    ) {}

    /**
     * @param  list<string>  $answers
     */
    public static function ok(int $latencyMs, ?string $ip, string $rcode, array $answers): self
    {
        return new self(true, $latencyMs, $ip, $rcode, $answers, null);
    }

    public static function failed(?int $latencyMs, string $message, ?string $ip = null): self
    {
        return new self(false, $latencyMs, $ip, null, [], $message);
    }

    public function body(): ?string
    {
        return $this->answers[0] ?? null;
    }
}
