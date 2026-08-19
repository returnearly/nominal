<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Monitors\MonitorResource;
use App\Models\Monitor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class DownMonitorsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '10s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Down monitors')
            ->query(
                Monitor::query()->where('status', 'down')->latest('last_status_changed_at'),
            )
            ->columns([
                TextColumn::make('name')->url(fn (Monitor $record): string => MonitorResource::getUrl('view', ['record' => $record])),
                TextColumn::make('target')->limit(40),
                TextColumn::make('consecutive_failures')->label('Failures'),
                TextColumn::make('last_checked_at')->since(),
            ])
            ->paginated(false);
    }
}
