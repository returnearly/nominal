<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConditionPlaceholder;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class DetectDomainExpirationCondition implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  iterable<int, mixed>|null  $expressions
     */
    public function handle(?iterable $expressions): bool
    {
        foreach ($expressions ?? [] as $expression) {
            if (is_string($expression) && str_contains($expression, ConditionPlaceholder::DomainExpiration->value)) {
                return true;
            }
        }

        return false;
    }
}
