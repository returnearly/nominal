<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Actions\SaveNotificationChannel;
use App\Actions\TestNotificationChannel;
use App\Enums\NotificationChannelType;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Support\NotificationChannelConfig;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EditNotificationChannel extends EditRecord
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Send test')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->tooltip('Uses the form values on this page, including unsaved changes.')
                ->action($this->sendTest(...)),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $type = $data['type'] ?? null;
        $type = $type instanceof NotificationChannelType
            ? $type
            : NotificationChannelType::tryFrom((string) $type);

        if ($type instanceof NotificationChannelType) {
            $data['config'] = NotificationChannelConfig::forForm($type, $data['config'] ?? []);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return SaveNotificationChannel::make()->handle($data, $record);
    }

    private function sendTest(): void
    {
        try {
            TestNotificationChannel::make()->handle(
                $this->channel(),
                $this->form->getState(shouldCallHooksBefore: false),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Test notification failed')
                ->body($exception->getMessage())
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Test notification sent')
            ->body('Check the destination for a message from Nominal.')
            ->send();
    }

    private function channel(): NotificationChannel
    {
        /** @var NotificationChannel $record */
        $record = $this->getRecord();

        return $record;
    }
}
