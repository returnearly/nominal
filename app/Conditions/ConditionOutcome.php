<?php

declare(strict_types=1);

namespace App\Conditions;

final readonly class ConditionOutcome
{
    public function __construct(
        public string $expression,
        public bool $passed,
        public mixed $actual,
    ) {}

    /**
     * @return array{expression: string, passed: bool, actual: mixed}
     */
    public function toArray(): array
    {
        return [
            'expression' => $this->expression,
            'passed' => $this->passed,
            'actual' => $this->stringify($this->actual),
        ];
    }

    private function stringify(mixed $value): mixed
    {
        if ($value instanceof PatternValue || $value instanceof AnyValue) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }
}
