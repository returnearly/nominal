<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors;

use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Monitors\Pages\EditMonitor;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Resources\Monitors\RelationManagers\CheckResultsRelationManager;
use App\Models\Monitor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class MonitorResource extends Resource
{
    protected static ?string $model = Monitor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $isHttp = self::isHttp(...);

        return $schema->components([
            Section::make('Monitor')
                ->columns(2)
                ->components([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('group')->maxLength(255),
                    Select::make('type')
                        ->options(MonitorType::class)
                        ->default(MonitorType::Http)
                        ->required()
                        ->live(),
                    TextInput::make('target')
                        ->required()
                        ->maxLength(2048)
                        ->placeholder('https://example.com/health'),
                    Select::make('method')
                        ->options(HttpMethod::class)
                        ->default(HttpMethod::Get)
                        ->visible($isHttp),
                    Select::make('ip_family')
                        ->options(IpFamily::class)
                        ->default(IpFamily::Any)
                        ->required(),
                    TextInput::make('interval_seconds')->numeric()->required()->default(60)->minValue(10),
                    TextInput::make('timeout_seconds')->numeric()->required()->default(10)->minValue(1),
                    TextInput::make('retention_days')->numeric()->required()->default(30)->minValue(1),
                    Toggle::make('enabled')->default(true),
                    Toggle::make('follow_redirects')
                        ->default(true)
                        ->visible($isHttp),
                    Toggle::make('verify_tls')
                        ->default(true)
                        ->visible($isHttp),
                ]),
            Section::make('HTTP request')
                ->visible($isHttp)
                ->components([
                    KeyValue::make('request_headers')
                        ->keyLabel('Header')
                        ->valueLabel('Value'),
                    Textarea::make('request_body')
                        ->rows(6)
                        ->columnSpanFull(),
                ]),
            Section::make('Conditions')
                ->components([
                    Repeater::make('conditions')
                        ->relationship()
                        ->schema([
                            TextInput::make('expression')
                                ->required()
                                ->placeholder('[STATUS] == 200'),
                        ])
                        ->orderColumn('sort')
                        ->defaultItems(1)
                        ->addActionLabel('Add condition')
                        ->columnSpanFull(),
                ]),
            Section::make('Routing')
                ->columns(2)
                ->components([
                    Select::make('probes')
                        ->relationship('probes', 'name')
                        ->multiple()
                        ->preload()
                        ->required(),
                    Select::make('notificationChannels')
                        ->relationship('notificationChannels', 'name')
                        ->multiple()
                        ->preload(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('group')->toggleable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (MonitorStatus $state): string => match ($state) {
                        MonitorStatus::Up => 'success',
                        MonitorStatus::Down => 'danger',
                        MonitorStatus::Paused => 'warning',
                        MonitorStatus::Pending => 'gray',
                    }),
                TextColumn::make('target')->limit(40)->toggleable(),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('last_checked_at')->since()->sortable(),
                TextColumn::make('next_check_at')->since()->sortable(),
            ])
            ->defaultSort('name')
            ->defaultGroup('group')
            ->groups([
                Group::make('group')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Monitor $record): string => filled($record->group) ? $record->group : 'Ungrouped'),
            ])
            ->paginated([50, 100, 250])
            ->defaultPaginationPageOption(50)
            ->filters([
                SelectFilter::make('status')->options(MonitorStatus::class),
                SelectFilter::make('type')->options(MonitorType::class),
                SelectFilter::make('group')->options(
                    fn (): array => Monitor::query()
                        ->whereNotNull('group')
                        ->distinct()
                        ->pluck('group', 'group')
                        ->all(),
                ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CheckResultsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitors::route('/'),
            'create' => CreateMonitor::route('/create'),
            'view' => ViewMonitor::route('/{record}'),
            'edit' => EditMonitor::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    private static function isHttp(Get $get): bool
    {
        $type = $get('type');

        return $type === MonitorType::Http || $type === MonitorType::Http->value;
    }
}
