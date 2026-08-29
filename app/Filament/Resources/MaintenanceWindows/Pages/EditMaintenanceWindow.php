<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaintenanceWindows\Pages;

use App\Actions\EndMaintenanceWindow;
use App\Actions\SaveMaintenanceWindow;
use App\Filament\Resources\MaintenanceWindows\MaintenanceWindowResource;
use App\Models\MaintenanceWindow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditMaintenanceWindow extends EditRecord
{
    protected static string $resource = MaintenanceWindowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('end')
                ->label(fn (): string => $this->window()->isScheduled() ? 'Cancel' : 'End now')
                ->visible(fn (): bool => $this->window()->isActive() || $this->window()->isScheduled())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record = EndMaintenanceWindow::make()->handle($this->window());
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return SaveMaintenanceWindow::make()->handle($data, $record instanceof MaintenanceWindow ? $record : null);
    }

    private function window(): MaintenanceWindow
    {
        /** @var MaintenanceWindow $record */
        $record = $this->getRecord();

        return $record;
    }
}
