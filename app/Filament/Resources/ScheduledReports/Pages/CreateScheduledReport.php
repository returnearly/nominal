<?php

declare(strict_types=1);

namespace App\Filament\Resources\ScheduledReports\Pages;

use App\Actions\SaveScheduledReport;
use App\Filament\Resources\ScheduledReports\ScheduledReportResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateScheduledReport extends CreateRecord
{
    protected static string $resource = ScheduledReportResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return SaveScheduledReport::make()->handle($data);
    }
}
