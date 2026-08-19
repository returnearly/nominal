<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\MonitorResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateMonitor extends CreateRecord
{
    protected static string $resource = MonitorResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->conditions()->doesntExist()) {
            $this->record->conditions()->create([
                'expression' => $this->record->type === MonitorType::Ping
                    ? '[CONNECTED] == true'
                    : '[STATUS] == 200',
                'sort' => 0,
            ]);
        }
    }
}
