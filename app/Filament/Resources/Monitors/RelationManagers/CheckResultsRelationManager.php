<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\RelationManagers;

use App\Filament\Concerns\RefreshesOnMonitorBroadcasts;
use App\Filament\Resources\CheckResults\CheckResultResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CheckResultsRelationManager extends RelationManager
{
    use RefreshesOnMonitorBroadcasts;

    protected static string $relationship = 'checkResults';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return CheckResultResource::configureHistoryTable($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('probe'))
            ->paginated([10])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
