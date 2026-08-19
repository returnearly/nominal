<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditNotificationChannel extends EditRecord
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
