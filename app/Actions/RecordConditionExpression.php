<?php

declare(strict_types=1);

namespace App\Actions;

use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class RecordConditionExpression implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(private ComposeConditionExpression $compose) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $data['expression'] = $this->compose->handle($data);
        unset($data['placeholder'], $data['path'], $data['comparator'], $data['value']);

        return $data;
    }
}
