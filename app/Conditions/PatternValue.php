<?php

declare(strict_types=1);

namespace App\Conditions;

final readonly class PatternValue
{
    public function __construct(public string $pattern) {}

    public function matches(mixed $value): bool
    {
        return fnmatch($this->pattern, (string) $value, FNM_CASEFOLD);
    }

    public function __toString(): string
    {
        return 'pat('.$this->pattern.')';
    }
}
