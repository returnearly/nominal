<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CheckResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkResults';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultSort('checked_at', 'desc')
            ->columns([
                IconColumn::make('success')->boolean(),
                TextColumn::make('checked_at')->dateTime()->sortable(),
                TextColumn::make('probe.name')->label('Probe'),
                TextColumn::make('http_status')->label('Status'),
                TextColumn::make('latency_ms')->suffix(' ms'),
                TextColumn::make('resolved_ip'),
                TextColumn::make('message')->limit(60)->wrap(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
