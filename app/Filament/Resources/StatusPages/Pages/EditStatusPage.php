<?php

declare(strict_types=1);

namespace App\Filament\Resources\StatusPages\Pages;

use App\Filament\Resources\StatusPages\StatusPageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditStatusPage extends EditRecord
{
    protected static string $resource = StatusPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Open page')
                ->url(fn (): string => $this->getRecord()->pathUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->getRecord()->published),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! ($this->data['password_protected'] ?? false)) {
            $data['password'] = null;
        }

        return $data;
    }
}
