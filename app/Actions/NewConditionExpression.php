<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class NewConditionExpression implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private DefaultConditionExpressions $defaults,
        private ParseConditionExpression $parse,
    ) {}

    /**
     * @return array{placeholder: string, path: string, comparator: string, value: string}
     */
    public function handle(mixed $type): array
    {
        $defaults = $this->defaults->handle($type);

        if ($defaults === []) {
            return [
                'placeholder' => ConditionPlaceholder::Connected->value,
                'path' => '',
                'comparator' => ConditionComparator::Equal->value,
                'value' => 'true',
            ];
        }

        return $this->parse->handle($defaults[0]);
    }
}
