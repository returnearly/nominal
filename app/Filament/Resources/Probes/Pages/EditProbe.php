<?php

declare(strict_types=1);

namespace App\Filament\Resources\Probes\Pages;

use App\Filament\Resources\Probes\ProbeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditProbe extends EditRecord
{
    protected static string $resource = ProbeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
