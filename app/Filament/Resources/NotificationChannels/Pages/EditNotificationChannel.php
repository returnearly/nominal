<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Actions\RegenerateBrowserChannelKeys;
use App\Actions\SaveNotificationChannel;
use App\Actions\SubscribePushDevice;
use App\Actions\TestNotificationChannel;
use App\Actions\UnsubscribePushDevice;
use App\Enums\NotificationChannelType;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Support\NotificationChannelConfig;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
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
            Action::make('regenerateKeys')
                ->label('Regenerate keys')
                ->icon(Heroicon::OutlinedKey)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Regenerate VAPID keys?')
                ->modalDescription('All devices will stop receiving push until they enable this channel again.')
                ->visible(fn (): bool => $this->channel()->type === NotificationChannelType::Browser)
                ->action(function (): void {
                    $this->record = RegenerateBrowserChannelKeys::make()->handle($this->channel());
                    $this->refreshFormData(['config']);

                    Notification::make()
                        ->success()
                        ->title('VAPID keys regenerated')
                        ->body('Devices must enable this channel again.')
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            View::make('filament.notification-channels.browser-devices')
                ->visible(fn (): bool => $this->channel()->type === NotificationChannelType::Browser),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /**
     * @param  array{endpoint: string, public_key: string, auth_token: string, content_encoding?: string, user_agent?: string|null}  $subscription
     */
    public function subscribeBrowserDevice(array $subscription): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        SubscribePushDevice::make()->handle($this->channel(), $subscription, $user);

        Notification::make()
            ->success()
            ->title('This device is enabled')
            ->send();
    }

    public function unsubscribeBrowserDevice(string $endpoint): void
    {
        UnsubscribePushDevice::make()->handle($this->channel(), $endpoint);

        Notification::make()
            ->success()
            ->title('This device is disabled')
            ->send();
    }

    public function removeBrowserDevice(string $subscriptionId): void
    {
        UnsubscribePushDevice::make()->byId($this->channel(), $subscriptionId);

        Notification::make()
            ->success()
            ->title('Device removed')
            ->send();
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
