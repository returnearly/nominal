<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Actions\SaveNotificationChannel;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateNotificationChannel extends CreateRecord
{
    protected static string $resource = NotificationChannelResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return SaveNotificationChannel::make()->handle($data);
    }
}
