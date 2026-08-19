<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use InvalidArgumentException;

final class EnumValue
{
    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    public static function parse(string $enum, mixed $value): BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        $raw = (string) $value;

        if (($match = $enum::tryFrom($raw)) !== null) {
            return $match;
        }

        if (($match = $enum::tryFrom(strtolower($raw))) !== null) {
            return $match;
        }

        if (($match = $enum::tryFrom(strtoupper($raw))) !== null) {
            return $match;
        }

        foreach ($enum::cases() as $case) {
            if (strcasecmp($case->name, $raw) === 0) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Invalid {$enum} [{$raw}].");
    }
}
