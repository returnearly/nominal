<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\DomainExpirationReader;
use App\Checking\DomainHostname;
use App\Models\Monitor;
use DateTimeImmutable;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class LookupDomainExpiration implements ActionsPatternInterface
{
    use ActionsPattern;

    public const MinimumIntervalSeconds = 300;

    public function __construct(
        private DomainExpirationReader $reader,
        private DetectDomainExpirationCondition $detectDomainExpiration,
    ) {}

    public function handle(Monitor $monitor): ?DateTimeImmutable
    {
        if (! $this->needed($monitor)) {
            return null;
        }

        $hostname = DomainHostname::fromMonitor($monitor);

        if ($hostname === null) {
            return null;
        }

        return $this->reader->expiresAt($hostname, $monitor->timeout_seconds);
    }

    private function needed(Monitor $monitor): bool
    {
        if (! $monitor->type->supportsDomainExpiration()) {
            return false;
        }

        return $this->detectDomainExpiration->handle(
            $monitor->conditions->pluck('expression'),
        );
    }
}
