<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateNotificationChannel extends CreateRecord
{
    protected static string $resource = NotificationChannelResource::class;
}
