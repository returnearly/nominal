<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Str;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class DefaultConditionFormState implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private DefaultConditionExpressions $defaults,
        private ParseConditionExpression $parse,
    ) {}

    /**
     * @return array<string, array{placeholder: string, path: string, comparator: string, value: string}>
     */
    public function handle(mixed $type): array
    {
        $items = [];

        foreach ($this->defaults->handle($type) as $expression) {
            $items[(string) Str::uuid()] = $this->parse->handle($expression);
        }

        return $items;
    }
}
