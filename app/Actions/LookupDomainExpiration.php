<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\DomainExpirationReader;
use App\Checking\DomainHostname;
use App\Enums\ConditionPlaceholder;
use App\Models\Monitor;
use DateTimeImmutable;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class LookupDomainExpiration implements ActionsPatternInterface
{
    use ActionsPattern;

    public const MinimumIntervalSeconds = 300;

    public function __construct(private DomainExpirationReader $reader) {}

    public function handle(Monitor $monitor): ?DateTimeImmutable
    {
        if (! self::needed($monitor)) {
            return null;
        }

        $hostname = DomainHostname::fromMonitor($monitor);

        if ($hostname === null) {
            return null;
        }

        return $this->reader->expiresAt($hostname, $monitor->timeout_seconds);
    }

    public function seconds(?DateTimeImmutable $expiresAt): ?int
    {
        return $expiresAt instanceof DateTimeImmutable
            ? $expiresAt->getTimestamp() - time()
            : null;
    }

    public static function needed(Monitor $monitor): bool
    {
        if (! $monitor->type->supportsDomainExpiration()) {
            return false;
        }

        foreach ($monitor->conditions as $condition) {
            if (str_contains($condition->expression, ConditionPlaceholder::DomainExpiration->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<int, mixed>|null  $expressions
     */
    public static function expressionsNeedLookup(?iterable $expressions): bool
    {
        foreach ($expressions ?? [] as $expression) {
            if (is_string($expression) && str_contains($expression, ConditionPlaceholder::DomainExpiration->value)) {
                return true;
            }
        }

        return false;
    }
}
