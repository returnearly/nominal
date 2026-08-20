<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Actions\DispatchMonitorCheck;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Filament\Widgets\MonitorHistoryWidget;
use App\Models\Monitor;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ViewMonitor extends ViewRecord
{
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
        $group = filled($record->group) ? $record->group : 'Ungrouped';

        return $group.' · '.($record->heartbeatUrl() ?? $record->target);
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

    private function queueCheck(): void
    {
        /** @var Monitor $monitor */
        $monitor = $this->getRecord();
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
