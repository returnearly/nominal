<?php

declare(strict_types=1);

namespace App\Filament\Resources\CheckResults\Pages;

use App\Filament\Resources\CheckResults\CheckResultResource;
use Filament\Resources\Pages\ListRecords;

final class ListCheckResults extends ListRecords
{
    protected static string $resource = CheckResultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
