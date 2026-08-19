<?php

declare(strict_types=1);

namespace App\Filament\Resources\Probes\Pages;

use App\Filament\Resources\Probes\ProbeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListProbes extends ListRecords
{
    protected static string $resource = ProbeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
