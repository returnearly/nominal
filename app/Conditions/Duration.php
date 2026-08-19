<?php

declare(strict_types=1);

namespace App\Conditions;

final class Duration
{
    public static function toSeconds(int $amount, string $unit): int
    {
        return match ($unit) {
            'ms' => (int) max(1, (int) ceil($amount / 1000)),
            's' => $amount,
            'm' => $amount * 60,
            'h' => $amount * 3600,
            'd' => $amount * 86400,
            default => throw new InvalidConditionException("Unknown duration unit [{$unit}]."),
        };
    }
}
