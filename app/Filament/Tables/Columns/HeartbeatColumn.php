<?php

declare(strict_types=1);

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\Column;

final class HeartbeatColumn extends Column
{
    protected string $view = 'filament.tables.columns.heartbeat';

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Last 20');
    }
}
