<?php

declare(strict_types=1);

namespace App\Filament\Resources\Probes\Pages;

use App\Filament\Resources\Probes\ProbeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateProbe extends CreateRecord
{
    protected static string $resource = ProbeResource::class;
}
