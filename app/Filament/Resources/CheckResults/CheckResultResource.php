<?php

declare(strict_types=1);

namespace App\Filament\Resources\CheckResults;

use App\Actions\FormatMilliseconds;
use App\Filament\Resources\CheckResults\Pages\ListCheckResults;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Models\CheckResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CheckResultResource extends Resource
{
    protected static ?string $model = CheckResult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'History';

    protected static ?string $pluralModelLabel = 'History';

    protected static ?string $modelLabel = 'Check';

    protected static ?string $slug = 'history';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return self::configureHistoryTable($table, includeMonitor: true)
            ->paginationMode(PaginationMode::Simple)
            ->recordUrl(fn (CheckResult $record): string => MonitorResource::getUrl('view', [
                'record' => $record->monitor_id,
            ]))
            ->filters([
                SelectFilter::make('success')
                    ->options([
                        '1' => 'Up',
                        '0' => 'Down',
                    ]),
                SelectFilter::make('monitor')
                    ->relationship('monitor', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function configureHistoryTable(Table $table, bool $includeMonitor = false): Table
    {
        return $table
            ->poll('10s')
            ->defaultSort('checked_at', 'desc')
            ->paginated([50, 100, 250])
            ->defaultPaginationPageOption(50)
            ->columns(self::historyColumns($includeMonitor));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['monitor', 'probe']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCheckResults::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * @return list<IconColumn|TextColumn>
     */
    private static function historyColumns(bool $includeMonitor = false): array
    {
        $columns = [
            IconColumn::make('success')->boolean(),
            TextColumn::make('checked_at')->dateTime()->sortable(),
        ];

        if ($includeMonitor) {
            $columns[] = TextColumn::make('monitor.name')
                ->label('Monitor')
                ->searchable()
                ->sortable();
        }

        $columns[] = TextColumn::make('probe.name')->label('Probe')->placeholder('Web');
        $columns[] = TextColumn::make('http_status')->label('Status');
        $columns[] = TextColumn::make('latency_ms')
            ->label('Latency')
            ->formatStateUsing(fn (?int $state): ?string => FormatMilliseconds::make()->handle($state));
        $columns[] = TextColumn::make('resolved_ip');
        $columns[] = TextColumn::make('message')->limit(60)->wrap();

        return $columns;
    }
}
