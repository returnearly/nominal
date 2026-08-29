<?php

declare(strict_types=1);

namespace App\Actions;

use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class FillConditionForm implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(private ParseConditionExpression $parse) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        return [
            ...$data,
            ...$this->parse->handle($data['expression'] ?? null),
        ];
    }
}
