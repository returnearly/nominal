<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Filament\Concerns\ShowsApiManagedNotice;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use App\Filament\Support\ApiManagedUi;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListNotificationChannels extends ListRecords
{
    use ShowsApiManagedNotice;

    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApiManagedUi::lock(CreateAction::make()),
        ];
    }
}
