<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Conditions\ConditionExpression;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\MonitorResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateMonitor extends CreateRecord
{
    protected static string $resource = MonitorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['type'] ?? null;
        $isHeartbeat = $type === MonitorType::Heartbeat || $type === MonitorType::Heartbeat->value;

        if ($isHeartbeat && blank($data['target'] ?? null)) {
            $data['target'] = 'heartbeat';
        }

        return $data;
    }

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
