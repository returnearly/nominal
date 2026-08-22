<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Enums\IpFamily;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\MonitorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditMonitor extends EditRecord
{
    protected static string $resource = MonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            MonitorResource::duplicateAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $type = $data['type'] ?? $this->record->type;
        $isHeartbeat = $type === MonitorType::Heartbeat
            || $type === MonitorType::Heartbeat->value
            || ($type instanceof MonitorType && $type->isHeartbeat());

        if ($isHeartbeat) {
            $data['ip_family'] = IpFamily::Any;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->record->type->isHeartbeat()) {
            return;
        }

        $this->record->conditions()->delete();
        $this->record->probes()->sync([]);
    }
}
