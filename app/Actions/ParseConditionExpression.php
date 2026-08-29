<?php

declare(strict_types=1);

namespace App\Actions;

use App\Conditions\InvalidConditionException;
use App\Enums\ConditionComparator;
use App\Enums\MonitorType;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ParseConditionExpression implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(private DefaultConditionExpressions $defaults) {}

    /**
     * @return array{placeholder: string, path: string, comparator: string, value: string}
     */
    public function handle(?string $expression): array
    {
        $expression = trim((string) $expression);

        if ($expression === '') {
            $expression = $this->defaults->handle(MonitorType::Http)[0];
        }

        try {
            [$left, $operator, $right] = $this->split($expression);
        } catch (InvalidConditionException) {
            return [
                'placeholder' => $expression,
                'path' => '',
                'comparator' => ConditionComparator::Equal->value,
                'value' => '',
            ];
        }

        $placeholder = $left;
        $path = '';

        if (preg_match('/^(\[[A-Z_]+\])(.*)$/', $left, $matches) === 1) {
            $placeholder = $matches[1];
            $path = $matches[2];
        }

        return [
            'placeholder' => $placeholder,
            'path' => $path,
            'comparator' => $operator,
            'value' => $right,
        ];
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
}
