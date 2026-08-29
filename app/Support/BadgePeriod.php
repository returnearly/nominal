<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final readonly class BadgePeriod
{
    public const DEFAULT = '24h';

    public function __construct(
        public string $key,
        public int $seconds,
    ) {}

    public static function parse(?string $value): self
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            $value = self::DEFAULT;
        }

        if (preg_match('/^(\d+)h$/', $value, $matches) === 1) {
            $hours = (int) $matches[1];

            if ($hours < 1 || $hours > 24 * 90) {
                throw new InvalidArgumentException('Invalid badge period.');
            }

            return new self($hours.'h', $hours * 3600);
        }

        if (preg_match('/^(\d+)d$/', $value, $matches) === 1) {
            $days = (int) $matches[1];

            if ($days < 1 || $days > 90) {
                throw new InvalidArgumentException('Invalid badge period.');
            }

            return new self($days.'d', $days * 86400);
        }

        throw new InvalidArgumentException('Invalid badge period.');
    }

    public function usesAggregates(): bool
    {
        return $this->seconds > 3600;
    }
}
