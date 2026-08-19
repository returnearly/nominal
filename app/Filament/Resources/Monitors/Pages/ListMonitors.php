<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\MonitorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListMonitors extends ListRecords
{
    protected static string $resource = MonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
