<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Actions\SaveNotificationChannel;
use App\Enums\NotificationChannelType;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use App\Support\NotificationChannelConfig;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditNotificationChannel extends EditRecord
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $type = $data['type'] ?? null;
        $type = $type instanceof NotificationChannelType
            ? $type
            : NotificationChannelType::tryFrom((string) $type);

        if ($type instanceof NotificationChannelType) {
            $data['config'] = NotificationChannelConfig::forForm($type, $data['config'] ?? []);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return SaveNotificationChannel::make()->handle($data, $record);
    }
}
