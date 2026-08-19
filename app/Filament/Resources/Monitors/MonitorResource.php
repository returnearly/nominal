<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors;

use App\Conditions\ConditionExpression;
use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Monitors\Pages\EditMonitor;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Resources\Monitors\RelationManagers\CheckResultsRelationManager;
use App\Filament\Tables\Columns\HeartbeatColumn;
use App\Models\Monitor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group as TableGroup;
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
                        ->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $set('conditions', ConditionExpression::defaultFormState($state));
                        }),
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
                ->description('These are what determine whether an endpoint is healthy or not.')
                ->components([
                    Repeater::make('conditions')
                        ->relationship()
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Placeholder')->markAsRequired(),
                            TableColumn::make('Comparator')->markAsRequired()->width('8rem'),
                            TableColumn::make('Value')->markAsRequired(),
                        ])
                        ->schema([
                            Group::make([
                                Select::make('placeholder')
                                    ->hiddenLabel()
                                    ->options(fn (Get $get): array => ConditionPlaceholder::options($get('placeholder')))
                                    ->default(fn (Get $get): string => ConditionExpression::newItem(self::monitorType($get))['placeholder'])
                                    ->required()
                                    ->native(false)
                                    ->selectablePlaceholder(false)
                                    ->live()
                                    ->afterStateUpdated(self::syncComparator(...)),
                                TextInput::make('path')
                                    ->hiddenLabel()
                                    ->placeholder('.status')
                                    ->visible(self::isBody(...)),
                            ]),
                            Select::make('comparator')
                                ->hiddenLabel()
                                ->options(fn (Get $get): array => ConditionPlaceholder::comparatorOptions(
                                    $get('placeholder'),
                                    $get('comparator'),
                                ))
                                ->default(fn (Get $get): string => ConditionExpression::newItem(self::monitorType($get))['comparator'])
                                ->required()
                                ->native(false)
                                ->selectablePlaceholder(false),
                            TextInput::make('value')
                                ->hiddenLabel()
                                ->default(fn (Get $get): string => ConditionExpression::newItem(self::monitorType($get))['value'])
                                ->required(),
                        ])
                        ->mutateRelationshipDataBeforeFillUsing(ConditionExpression::toForm(...))
                        ->mutateRelationshipDataBeforeCreateUsing(ConditionExpression::toRecord(...))
                        ->mutateRelationshipDataBeforeSaveUsing(ConditionExpression::toRecord(...))
                        ->orderColumn('sort')
                        ->default(fn (Get $get): array => ConditionExpression::defaultsForType($get('type')))
                        ->minItems(1)
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
                TextColumn::make('status')->badge(),
                HeartbeatColumn::make('heartbeat'),
                TextColumn::make('target')->limit(40)->toggleable(),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('last_checked_at')->since()->sortable(),
                TextColumn::make('next_check_at')->since()->sortable(),
            ])
            ->defaultSort('name')
            ->defaultGroup('group')
            ->groups([
                TableGroup::make('group')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
                    ->getTitleFromRecordUsing(self::groupTitle(...)),
            ])
            ->paginated([50, 100, 250])
            ->defaultPaginationPageOption(50)
            ->filters([
                SelectFilter::make('status')->options(MonitorStatus::class),
                SelectFilter::make('type')->options(MonitorType::class),
                SelectFilter::make('group')->options(self::groupOptions(...)),
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
        $type = self::monitorType($get);

        return $type === MonitorType::Http || $type === MonitorType::Http->value;
    }

    private static function isBody(Get $get): bool
    {
        return $get('placeholder') === ConditionPlaceholder::Body->value;
    }

    private static function syncComparator(Set $set, Get $get, mixed $state): void
    {
        $options = ConditionPlaceholder::comparatorOptions($state);
        $current = $get('comparator');
        $current = $current instanceof BackedEnum ? (string) $current->value : trim((string) $current);

        if ($current === '' || ! array_key_exists($current, $options)) {
            $set('comparator', array_key_first($options) ?: ConditionComparator::Equal->value);
        }

        if (! blank($get('value'))) {
            return;
        }

        $placeholder = $state instanceof ConditionPlaceholder
            ? $state
            : ConditionPlaceholder::tryFrom(trim((string) $state));

        if ($placeholder !== null && $placeholder->defaultValue() !== '') {
            $set('value', $placeholder->defaultValue());
        }
    }

    private static function monitorType(Get $get): mixed
    {
        return $get('type') ?? $get('../../type') ?? $get('../../../type');
    }

    private static function groupTitle(Monitor $record): string
    {
        return blank($record->group) ? 'Ungrouped' : $record->group;
    }

    /**
     * @return array<string, string>
     */
    private static function groupOptions(): array
    {
        return Monitor::query()
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group', 'group')
            ->all();
    }
}
