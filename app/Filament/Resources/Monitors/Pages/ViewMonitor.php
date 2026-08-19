<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Actions\DispatchMonitorCheck;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Models\Monitor;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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
                ->action(function (): void {
                    /** @var Monitor $monitor */
                    $monitor = $this->getRecord();
                    $count = DispatchMonitorCheck::make()->handle($monitor);

                    if ($count === 0) {
                        Notification::make()
                            ->warning()
                            ->title('No enabled probes assigned')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title($count === 1 ? 'Check queued' : "{$count} checks queued")
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
