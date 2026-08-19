<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Conditions\ConditionExpression;
use App\Filament\Resources\Monitors\MonitorResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateMonitor extends CreateRecord
{
    protected static string $resource = MonitorResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->conditions()->doesntExist()) {
            foreach (ConditionExpression::defaultExpressions($this->record->type) as $sort => $expression) {
                $this->record->conditions()->create([
                    'expression' => $expression,
                    'sort' => $sort,
                ]);
            }
        }
    }
}
