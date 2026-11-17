<?php

declare(strict_types=1);

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\Column;

final class MonitorCardColumn extends Column
{
    protected string $view = 'filament.tables.columns.monitor-card';

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Monitor')->sortable();
    }
}
