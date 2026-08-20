<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors;

use App\Conditions\ConditionExpression;
use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\DnsQueryType;
use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Monitors\Pages\EditMonitor;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Resources\Monitors\RelationManagers\CheckResultsRelationManager;
use App\Filament\Tables\Columns\MonitorCardColumn;
use App\Models\Monitor;
use App\Models\Probe;
use App\Support\MonitorTags;
use App\Support\ProxyUrl;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;

final class MonitorResource extends Resource
{
    protected static ?string $model = Monitor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $usesHttp = self::usesHttpRequest(...);
        $usesRequestBody = self::usesRequestBody(...);
        $usesRequestHeaders = self::usesRequestHeaders(...);
        $usesVerifyTls = self::usesVerifyTls(...);
        $usesProxy = self::usesProxy(...);

        return $schema->components([
            Section::make('Monitor')
                ->columns(2)
                ->components([
                    TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                    TagsInput::make('tags')
                        ->suggestions(self::tagSuggestions(...))
                        ->nestedRecursiveRules(['max:'.MonitorTags::MaxLength])
                        ->columnSpanFull()
                        ->helperText('Filter labels. A monitor can have several.'),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull()
                        ->maxLength(4000)
                        ->helperText('What this is, who owns it, and what to do when it fails.'),
                    Select::make('type')
                        ->options(MonitorType::class)
                        ->default(MonitorType::Http)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $set('conditions', ConditionExpression::defaultFormState($state));

                            $type = $state instanceof MonitorType
                                ? $state
                                : MonitorType::tryFrom((string) $state);

                            if ($type === null) {
                                return;
                            }

                            if (! $type->usesOutboundProbe()) {
                                $set('probes', []);
                                $set('ip_family', IpFamily::Any);
                            }

                            if ($type->wrapsGraphQLBody()) {
                                $set('method', HttpMethod::Post);
                            } elseif (! $type->usesHttpRequest()) {
                                $set('method', null);
                                $set('follow_redirects', true);
                            }

                            if (! $type->usesRequestHeaders()) {
                                $set('request_headers', []);
                            }

                            if (! $type->usesRequestBody()) {
                                $set('request_body', null);
                            }

                            if (! $type->usesVerifyTls()) {
                                $set('verify_tls', true);
                            }

                            if (! $type->usesProxy()) {
                                $set('proxy_url', null);
                            }

                            if (! $type->usesDnsQuery()) {
                                $set('dns_query_name', null);
                                $set('dns_query_type', null);
                            }
                        }),
                    TextInput::make('target')
                        ->required(fn (Get $get): bool => self::type($get)?->isHeartbeat() !== true)
                        ->maxLength(2048)
                        ->placeholder(fn (Get $get): string => match (self::type($get)) {
                            MonitorType::Tcp => 'tcp://db.example.com:5432',
                            MonitorType::Udp => 'udp://dns.example.com:53',
                            MonitorType::Tls => 'tls://db.example.com:5432',
                            MonitorType::Dns => '1.1.1.1',
                            MonitorType::Ping => 'example.com',
                            MonitorType::Heartbeat => 'backup-job',
                            MonitorType::WebSocket => 'wss://example.com/socket',
                            MonitorType::GraphQL => 'https://countries.trevorblades.com/',
                            default => 'https://example.com/health',
                        }),
                    TextInput::make('heartbeat_url')
                        ->label('Heartbeat URL')
                        ->disabled()
                        ->copyable()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => self::type($get)?->isHeartbeat() === true)
                        ->afterStateHydrated(function (TextInput $component, mixed $record): void {
                            if ($record instanceof Monitor) {
                                $component->state($record->heartbeatUrl());
                            }
                        })
                        ->helperText('GET or POST this URL to signal success. Append /start, /finish, or /error to measure how long a job runs.'),
                    TextInput::make('heartbeat_start_url')
                        ->label('Start URL')
                        ->disabled()
                        ->copyable()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => self::type($get)?->isHeartbeat() === true)
                        ->afterStateHydrated(function (TextInput $component, mixed $record): void {
                            if ($record instanceof Monitor) {
                                $component->state($record->heartbeatStartUrl());
                            }
                        }),
                    TextInput::make('heartbeat_finish_url')
                        ->label('Finish URL')
                        ->disabled()
                        ->copyable()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => self::type($get)?->isHeartbeat() === true)
                        ->afterStateHydrated(function (TextInput $component, mixed $record): void {
                            if ($record instanceof Monitor) {
                                $component->state($record->heartbeatFinishUrl());
                            }
                        }),
                    TextInput::make('heartbeat_error_url')
                        ->label('Error URL')
                        ->disabled()
                        ->copyable()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => self::type($get)?->isHeartbeat() === true)
                        ->afterStateHydrated(function (TextInput $component, mixed $record): void {
                            if ($record instanceof Monitor) {
                                $component->state($record->heartbeatErrorUrl());
                            }
                        }),
                    TextInput::make('dns_query_name')
                        ->label('Query name')
                        ->maxLength(255)
                        ->placeholder('example.com')
                        ->required(fn (Get $get): bool => self::type($get) === MonitorType::Dns)
                        ->visible(self::usesDnsQuery(...)),
                    Select::make('dns_query_type')
                        ->label('Query type')
                        ->options(DnsQueryType::class)
                        ->default(DnsQueryType::A)
                        ->required(fn (Get $get): bool => self::type($get) === MonitorType::Dns)
                        ->visible(self::usesDnsQuery(...)),
                    Select::make('method')
                        ->options(HttpMethod::class)
                        ->default(fn (Get $get): HttpMethod => self::type($get)?->wrapsGraphQLBody() === true
                            ? HttpMethod::Post
                            : HttpMethod::Get)
                        ->visible($usesHttp),
                    Select::make('ip_family')
                        ->options(IpFamily::class)
                        ->default(IpFamily::Any)
                        ->required(self::usesOutboundProbe(...))
                        ->visible(self::usesOutboundProbe(...))
                        ->dehydrated(self::usesOutboundProbe(...)),
                    TextInput::make('interval_seconds')
                        ->numeric()
                        ->required()
                        ->default(60)
                        ->minValue(10)
                        ->helperText(fn (Get $get): ?string => self::type($get)?->isHeartbeat() === true
                            ? 'How often a heartbeat is expected. After /start, the job must finish within this interval.'
                            : null),
                    TextInput::make('timeout_seconds')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->required(self::usesOutboundProbe(...))
                        ->visible(self::usesOutboundProbe(...))
                        ->dehydrated(self::usesOutboundProbe(...)),
                    TextInput::make('retention_days')->numeric()->required()->default(30)->minValue(1),
                    Toggle::make('enabled')->default(true),
                    Toggle::make('follow_redirects')
                        ->default(true)
                        ->visible($usesHttp),
                    Toggle::make('verify_tls')
                        ->default(true)
                        ->visible($usesVerifyTls),
                    TextInput::make('proxy_url')
                        ->label('Proxy URL')
                        ->maxLength(2048)
                        ->placeholder('socks5h://127.0.0.1:1080')
                        ->helperText('HTTP (`http://proxy:8080`) or SOCKS (`socks5://`, `socks5h://`). Leave blank to use HTTP_PROXY / ALL_PROXY for HTTP checks.')
                        ->dehydrateStateUsing(fn (mixed $state): ?string => filled($state) ? (string) $state : null)
                        ->rule(function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! filled($value)) {
                                    return;
                                }

                                try {
                                    ProxyUrl::parse((string) $value);
                                } catch (InvalidArgumentException $exception) {
                                    $fail($exception->getMessage());
                                }
                            };
                        })
                        ->visible($usesProxy)
                        ->columnSpanFull(),
                ]),
            Section::make('Request')
                ->visible(fn (Get $get): bool => $usesRequestBody($get) || $usesRequestHeaders($get))
                ->components([
                    KeyValue::make('request_headers')
                        ->keyLabel('Header')
                        ->valueLabel('Value')
                        ->visible($usesRequestHeaders),
                    Textarea::make('request_body')
                        ->rows(6)
                        ->columnSpanFull()
                        ->helperText(fn (Get $get): ?string => match (self::type($get)) {
                            MonitorType::GraphQL => 'Sent as {"query": "..."} with Content-Type application/json.',
                            MonitorType::Http => null,
                            default => 'Optional payload written after the connection is established.',
                        }),
                ]),
            Section::make('Conditions')
                ->description('These are what determine whether an endpoint is healthy or not.')
                ->visible(self::usesOutboundProbe(...))
                ->dehydrated(self::usesOutboundProbe(...))
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
                                    ->options(fn (Get $get): array => ConditionPlaceholder::options(
                                        $get('placeholder'),
                                        self::monitorType($get),
                                    ))
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
                        ->minItems(fn (Get $get): int => self::usesOutboundProbe($get) ? 1 : 0)
                        ->dehydrated(self::usesOutboundProbe(...))
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
                        ->default(fn (): array => Probe::defaultIds())
                        ->required(self::usesOutboundProbe(...))
                        ->visible(self::usesOutboundProbe(...))
                        ->dehydrated(self::usesOutboundProbe(...)),
                    Select::make('notificationChannels')
                        ->relationship('notificationChannels', 'name')
                        ->multiple()
                        ->preload(),
                ]),
            Section::make('Badges')
                ->description('Public SVG and JSON badges for READMEs, Shields.io, and status pages.')
                ->visible(fn (?Monitor $record): bool => $record instanceof Monitor)
                ->components([
                    self::badgeUrlInput('status_badge_url', 'Status SVG', fn (Monitor $monitor): string => $monitor->statusBadgeSvgUrl()),
                    self::badgeUrlInput('status_badge_json_url', 'Status JSON', fn (Monitor $monitor): string => $monitor->statusBadgeJsonUrl()),
                    self::badgeUrlInput('uptime_badge_url', 'Uptime 24h SVG', fn (Monitor $monitor): string => $monitor->uptimeBadgeSvgUrl()),
                    self::badgeUrlInput('latency_badge_url', 'Latency 24h SVG', fn (Monitor $monitor): string => $monitor->latencyBadgeSvgUrl()),
                    TextInput::make('badge_markdown')
                        ->label('Markdown')
                        ->disabled()
                        ->copyable()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->afterStateHydrated(function (TextInput $component, mixed $record): void {
                            if ($record instanceof Monitor) {
                                $component->state($record->badgeMarkdown());
                            }
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                Stack::make([
                    MonitorCardColumn::make('name'),
                ]),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 4,
            ])
            ->recordUrl(fn (Monitor $record): string => self::getUrl('view', ['record' => $record]))
            ->defaultSort('name')
            ->selectable(false)
            ->paginated([50, 100, 250])
            ->defaultPaginationPageOption(50)
            ->filters([
                SelectFilter::make('status')
                    ->options(MonitorStatus::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        if ($value === MonitorStatus::Maintenance->value) {
                            return $query->underMaintenance();
                        }

                        return $query->notUnderMaintenance()->where('status', $value);
                    }),
                SelectFilter::make('type')->options(MonitorType::class),
                SelectFilter::make('tag')
                    ->label('Tag')
                    ->options(self::tagOptions(...))
                    ->query(function (Builder $query, array $data): Builder {
                        $tag = $data['value'] ?? null;

                        if (! is_string($tag) || $tag === '') {
                            return $query;
                        }

                        return $query->tagged($tag);
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
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

    private static function usesOutboundProbe(Get $get): bool
    {
        return self::type($get)?->usesOutboundProbe() ?? true;
    }

    private static function usesHttpRequest(Get $get): bool
    {
        return self::type($get)?->usesHttpRequest() ?? false;
    }

    private static function usesRequestBody(Get $get): bool
    {
        return self::type($get)?->usesRequestBody() ?? false;
    }

    private static function usesDnsQuery(Get $get): bool
    {
        return self::type($get)?->usesDnsQuery() ?? false;
    }

    private static function usesVerifyTls(Get $get): bool
    {
        return self::type($get)?->usesVerifyTls() ?? false;
    }

    private static function usesProxy(Get $get): bool
    {
        return self::type($get)?->usesProxy() ?? false;
    }

    private static function usesRequestHeaders(Get $get): bool
    {
        return self::type($get)?->usesRequestHeaders() ?? false;
    }

    private static function type(Get $get): ?MonitorType
    {
        $type = self::monitorType($get);

        if ($type instanceof MonitorType) {
            return $type;
        }

        return MonitorType::tryFrom((string) $type);
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

    /**
     * @param  callable(Monitor): string  $url
     */
    private static function badgeUrlInput(string $name, string $label, callable $url): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->disabled()
            ->copyable()
            ->dehydrated(false)
            ->afterStateHydrated(function (TextInput $component, mixed $record) use ($url): void {
                if ($record instanceof Monitor) {
                    $component->state($url($record));
                }
            });
    }

    /**
     * @return array<string, string>
     */
    private static function tagOptions(): array
    {
        return Monitor::query()
            ->pluck('tags')
            ->flatten()
            ->filter(fn (mixed $tag): bool => is_string($tag) && $tag !== '')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $tag): array => [$tag => $tag])
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function tagSuggestions(): array
    {
        return array_values(self::tagOptions());
    }
}
