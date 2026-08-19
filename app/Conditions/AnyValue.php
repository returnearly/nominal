<?php

declare(strict_types=1);

namespace App\Conditions;

final readonly class AnyValue
{
    /**
     * @param  list<mixed>  $values
     */
    public function __construct(public array $values) {}

    public function contains(mixed $value): bool
    {
        foreach ($this->values as $candidate) {
            if ($this->equals($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return 'any('.implode(', ', array_map(strval(...), $this->values)).')';
    }

    private function equals(mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }
}
