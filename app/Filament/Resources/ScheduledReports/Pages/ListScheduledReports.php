<?php

declare(strict_types=1);

namespace App\Filament\Resources\ScheduledReports\Pages;

use App\Filament\Resources\ScheduledReports\ScheduledReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListScheduledReports extends ListRecords
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
