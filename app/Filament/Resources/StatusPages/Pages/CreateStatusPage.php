<?php

declare(strict_types=1);

namespace App\Filament\Resources\StatusPages\Pages;

use App\Filament\Resources\StatusPages\StatusPageResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateStatusPage extends CreateRecord
{
    protected static string $resource = StatusPageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! ($this->data['password_protected'] ?? false)) {
            $data['password'] = null;
        }

        return $data;
    }
}
