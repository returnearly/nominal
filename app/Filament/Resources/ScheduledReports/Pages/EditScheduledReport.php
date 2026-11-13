<?php

declare(strict_types=1);

namespace App\Filament\Resources\ScheduledReports\Pages;

use App\Actions\SaveScheduledReport;
use App\Filament\Resources\ScheduledReports\ScheduledReportResource;
use App\Models\ScheduledReport;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditScheduledReport extends EditRecord
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return SaveScheduledReport::make()->handle($data, $record instanceof ScheduledReport ? $record : null);
    }
}
