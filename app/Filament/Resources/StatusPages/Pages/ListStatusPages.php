<?php

declare(strict_types=1);

namespace App\Filament\Resources\StatusPages\Pages;

use App\Filament\Resources\StatusPages\StatusPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListStatusPages extends ListRecords
{
    protected static string $resource = StatusPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
