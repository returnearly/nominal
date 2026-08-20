<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaintenanceWindows\Pages;

use App\Actions\SaveMaintenanceWindow;
use App\Filament\Resources\MaintenanceWindows\MaintenanceWindowResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateMaintenanceWindow extends CreateRecord
{
    protected static string $resource = MaintenanceWindowResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return SaveMaintenanceWindow::make()->handle($data);
    }
}
