<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use BackedEnum;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ComposeConditionExpression implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): string
    {
        $placeholder = $this->string($data['placeholder'] ?? '');
        $path = $this->normalizePath($placeholder, $this->string($data['path'] ?? ''));
        $comparator = $this->string($data['comparator'] ?? ConditionComparator::Equal->value);
        $value = $this->string($data['value'] ?? '');

        return trim("{$placeholder}{$path} {$comparator} {$value}");
    }

    private function normalizePath(string $placeholder, string $path): string
    {
        $path = trim($path);

        if ($placeholder !== ConditionPlaceholder::Body->value || $path === '') {
            return '';
        }

        if (! str_starts_with($path, '.') && ! str_starts_with($path, '[')) {
            return '.'.$path;
        }

        return $path;
    }

    private function string(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return trim((string) $value->value);
        }

        return trim((string) $value);
    }
}
