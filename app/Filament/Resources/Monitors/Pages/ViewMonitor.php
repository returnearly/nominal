<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Actions\DispatchMonitorCheck;
use App\Actions\EndMonitorMaintenance;
use App\Actions\StartMonitorMaintenance;
use App\Filament\Concerns\RefreshesOnMonitorBroadcasts;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Filament\Widgets\MonitorHistoryWidget;
use App\Models\Monitor;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ViewMonitor extends ViewRecord
{
    use RefreshesOnMonitorBroadcasts;

    protected static string $resource = MonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkNow')
                ->label('Check now')
                ->icon(Heroicon::OutlinedPlay)
                ->visible(function (): bool {
                    /** @var Monitor $record */
                    $record = $this->getRecord();

                    return $record->type->usesOutboundProbe();
                })
                ->action($this->queueCheck(...)),
            Action::make('startMaintenance')
                ->label('Start maintenance')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->visible(fn (): bool => ! $this->monitor()->hasDirectMaintenance())
                ->schema([
                    TextInput::make('title')->required()->maxLength(255)->default('Maintenance'),
                    Textarea::make('message')->rows(3),
                    DateTimePicker::make('ends_at')
                        ->label('Ends at')
                        ->seconds(false)
                        ->helperText('Leave empty to keep maintenance on until you end it.'),
                ])
                ->action(function (array $data): void {
                    StartMonitorMaintenance::make()->handle($this->monitor(), $data);
                    $this->refreshRecord();

                    Notification::make()
                        ->success()
                        ->title('Maintenance started')
                        ->send();
                }),
            Action::make('endMaintenance')
                ->label('End maintenance')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->monitor()->hasDirectMaintenance())
                ->requiresConfirmation()
                ->action(function (): void {
                    EndMonitorMaintenance::make()->handle($this->monitor());
                    $this->refreshRecord();

                    Notification::make()
                        ->success()
                        ->title('Maintenance ended')
                        ->send();
                }),
            MonitorResource::duplicateAction(),
            EditAction::make(),
        ];
    }

    protected function hasInfolist(): bool
    {
        return false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getRelationManagersContentComponent(),
            ]);
    }

    public function getSubheading(): ?string
    {
        /** @var Monitor $record */
        $record = $this->getRecord();

        return $record->heartbeatUrl() ?? $record->displayTarget();
    }

    /**
     * @return array<class-string<MonitorHistoryWidget>>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MonitorHistoryWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }

    protected function onMonitorBroadcast(): void
    {
        $this->refreshRecord();
    }

    private function monitor(): Monitor
    {
        /** @var Monitor $record */
        $record = $this->getRecord();

        return $record;
    }

    private function refreshRecord(): void
    {
        $this->record = $this->monitor()->fresh() ?? $this->monitor();
        $this->record->unsetRelation('activeMaintenanceWindow');
    }

    private function queueCheck(): void
    {
        $monitor = $this->monitor();
        $queued = DispatchMonitorCheck::make()->handle($monitor);

        if ($queued === 0) {
            Notification::make()
                ->warning()
                ->title('No enabled probes assigned')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($queued === 1 ? 'Check queued' : "{$queued} checks queued")
            ->send();
    }
}
