<?php

declare(strict_types=1);

namespace App\Conditions;

final class ConditionEvaluator
{
    /**
     * @param  list<string>  $expressions
     * @return list<ConditionOutcome>
     */
    public function evaluateAll(array $expressions, CheckContext $context): array
    {
        return array_map(
            fn (string $expression): ConditionOutcome => $this->evaluate($expression, $context),
            $expressions,
        );
    }

    public function allPassed(array $outcomes): bool
    {
        foreach ($outcomes as $outcome) {
            if (! $outcome->passed) {
                return false;
            }
        }

        return $outcomes !== [];
    }

    public function evaluate(string $expression, CheckContext $context): ConditionOutcome
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new InvalidConditionException('Condition expression cannot be empty.');
        }

        [$left, $operator, $right] = $this->split($expression);

        try {
            $leftValue = $this->evaluateSide($left, $context);
            $rightValue = $this->evaluateSide($right, $context);
            $passed = $this->compare($leftValue, $operator, $rightValue);
        } catch (UnresolvedPathException) {
            return new ConditionOutcome($expression, false, null);
        }

        return new ConditionOutcome($expression, $passed, $leftValue);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function split(string $expression): array
    {
        $operators = ['==', '!=', '<=', '>=', '<', '>'];
        $depth = 0;
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $character = $expression[$i];

            if ($character === '(') {
                $depth++;

                continue;
            }

            if ($character === ')') {
                $depth--;

                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            foreach ($operators as $operator) {
                if (substr($expression, $i, strlen($operator)) === $operator) {
                    return [
                        trim(substr($expression, 0, $i)),
                        $operator,
                        trim(substr($expression, $i + strlen($operator))),
                    ];
                }
            }
        }

        throw new InvalidConditionException("Missing comparator in [{$expression}].");
    }

    private function evaluateSide(string $side, CheckContext $context): mixed
    {
        $side = trim($side);

        if ($side === '') {
            throw new InvalidConditionException('Empty condition side.');
        }

        if (str_starts_with($side, 'len(') && str_ends_with($side, ')')) {
            return $this->length($this->evaluateSide(substr($side, 4, -1), $context));
        }

        if (str_starts_with($side, 'has(') && str_ends_with($side, ')')) {
            try {
                $this->evaluateSide(substr($side, 4, -1), $context);

                return true;
            } catch (UnresolvedPathException) {
                return false;
            }
        }

        if (str_starts_with($side, 'pat(') && str_ends_with($side, ')')) {
            return new PatternValue(substr($side, 4, -1));
        }

        if (str_starts_with($side, 'any(') && str_ends_with($side, ')')) {
            $inner = substr($side, 4, -1);
            $parts = $this->splitArguments($inner);

            return new AnyValue(array_map(
                fn (string $part): mixed => $this->evaluateSide($part, $context),
                $parts,
            ));
        }

        if (str_starts_with($side, '[')) {
            return $this->resolvePlaceholder($side, $context);
        }

        return $this->parseLiteral($side);
    }

    private function resolvePlaceholder(string $token, CheckContext $context): mixed
    {
        if (preg_match('/^\[([A-Z_]+)\](.*)$/', $token, $matches) !== 1) {
            throw new InvalidConditionException("Invalid placeholder [{$token}].");
        }

        $name = $matches[1];
        $path = $matches[2];

        return match ($name) {
            'STATUS' => $context->status,
            'RESPONSE_TIME' => $context->responseTimeMs,
            'IP' => $context->ip,
            'CONNECTED' => $context->connected,
            'CERTIFICATE_EXPIRATION' => $context->certificateExpirationSeconds,
            'BODY' => $this->resolveBody($path, $context),
            default => throw new InvalidConditionException("Unknown placeholder [{$name}]."),
        };
    }

    private function resolveBody(string $path, CheckContext $context): mixed
    {
        if ($path === '') {
            return $context->body ?? $context->rawBody;
        }

        if (! str_starts_with($path, '.')) {
            throw new InvalidConditionException("Invalid body path [{$path}].");
        }

        $value = $context->body;

        if (! is_array($value) && ! is_object($value)) {
            throw new UnresolvedPathException($path);
        }

        return JsonPath::get($value, ltrim($path, '.'));
    }

    private function parseLiteral(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === 'null') {
            return null;
        }

        if (preg_match('/^(\d+)(ms|s|m|h|d)$/', $value, $matches) === 1) {
            return Duration::toSeconds((int) $matches[1], $matches[2]);
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function compare(mixed $left, string $operator, mixed $right): bool
    {
        if ($right instanceof AnyValue) {
            return match ($operator) {
                '==' => $right->contains($left),
                '!=' => ! $right->contains($left),
                default => throw new InvalidConditionException('any() only supports == and !=.'),
            };
        }

        if ($left instanceof AnyValue) {
            return match ($operator) {
                '==' => $left->contains($right),
                '!=' => ! $left->contains($right),
                default => throw new InvalidConditionException('any() only supports == and !=.'),
            };
        }

        if ($right instanceof PatternValue) {
            return match ($operator) {
                '==' => $right->matches($left),
                '!=' => ! $right->matches($left),
                default => throw new InvalidConditionException('pat() only supports == and !=.'),
            };
        }

        if ($left instanceof PatternValue) {
            return match ($operator) {
                '==' => $left->matches($right),
                '!=' => ! $left->matches($right),
                default => throw new InvalidConditionException('pat() only supports == and !=.'),
            };
        }

        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            if ($left === null || $right === null) {
                return false;
            }

            return match ($operator) {
                '<' => $left < $right,
                '<=' => $left <= $right,
                '>' => $left > $right,
                '>=' => $left >= $right,
            };
        }

        $equal = is_numeric($left) && is_numeric($right)
            ? (float) $left === (float) $right
            : (string) $left === (string) $right;

        return $operator === '==' ? $equal : ! $equal;
    }

    private function length(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if (is_object($value)) {
            return strlen(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        }

        return strlen((string) $value);
    }

    /**
     * @return list<string>
     */
    private function splitArguments(string $inner): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        foreach (str_split($inner) as $character) {
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $parts[] = trim($current);

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }
}
