<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListNotificationChannels extends ListRecords
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
