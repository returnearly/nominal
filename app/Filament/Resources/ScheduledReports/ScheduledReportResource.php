<?php

declare(strict_types=1);

namespace App\Filament\Resources\ScheduledReports;

use App\Actions\SendScheduledReport;
use App\Enums\ScheduledReportCadence;
use App\Enums\ScheduledReportScope;
use App\Enums\ScheduledReportType;
use App\Filament\Resources\ScheduledReports\Pages\CreateScheduledReport;
use App\Filament\Resources\ScheduledReports\Pages\EditScheduledReport;
use App\Filament\Resources\ScheduledReports\Pages\ListScheduledReports;
use App\Models\Monitor;
use App\Models\ScheduledReport;
use App\Support\MonitorTags;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ScheduledReportResource extends Resource
{
    protected static ?string $model = ScheduledReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Scheduled reports';

    protected static ?string $modelLabel = 'scheduled report';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'scheduled-reports';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Report')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->options(ScheduledReportType::class)
                            ->default(ScheduledReportType::Uptime)
                            ->required(),
                        Toggle::make('enabled')
                            ->default(true),
                        TagsInput::make('recipients')
                            ->label('To')
                            ->placeholder('ops@example.com')
                            ->required()
                            ->nestedRecursiveRules(['email', 'max:255'])
                            ->columnSpanFull()
                            ->helperText('One message is sent to every address.'),
                    ]),
                Section::make('When to send')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('cadence')
                            ->options(ScheduledReportCadence::class)
                            ->default(ScheduledReportCadence::Weekly)
                            ->required()
                            ->live(),
                        TimePicker::make('send_time')
                            ->label('Send time')
                            ->seconds(false)
                            ->required()
                            ->default('08:00')
                            ->helperText('Times use the app timezone ('.config('app.timezone').').'),
                        Select::make('weekday')
                            ->options(self::weekdays())
                            ->default(1)
                            ->visible(fn (Get $get): bool => self::is($get('cadence'), ScheduledReportCadence::Weekly))
                            ->required(fn (Get $get): bool => self::is($get('cadence'), ScheduledReportCadence::Weekly)),
                        Select::make('day_of_month')
                            ->label('Day of month')
                            ->options(array_combine(range(1, 28), range(1, 28)))
                            ->default(1)
                            ->visible(fn (Get $get): bool => self::is($get('cadence'), ScheduledReportCadence::Monthly))
                            ->required(fn (Get $get): bool => self::is($get('cadence'), ScheduledReportCadence::Monthly))
                            ->helperText('Days after the 28th are not used, so February always has this day.'),
                    ]),
                Section::make('Monitors')
                    ->columnSpanFull()
                    ->components([
                        Select::make('scope')
                            ->options(ScheduledReportScope::class)
                            ->default(ScheduledReportScope::All)
                            ->required()
                            ->live(),
                        Select::make('monitor_ids')
                            ->label('Monitors')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(fn (): array => Monitor::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->visible(fn (Get $get): bool => self::is($get('scope'), ScheduledReportScope::Monitors))
                            ->required(fn (Get $get): bool => self::is($get('scope'), ScheduledReportScope::Monitors))
                            ->dehydrated(fn (Get $get): bool => self::is($get('scope'), ScheduledReportScope::Monitors))
                            ->afterStateHydrated(function (Select $component, mixed $record): void {
                                if ($record instanceof ScheduledReport) {
                                    $component->state($record->monitors()->pluck('monitors.id')->all());
                                }
                            }),
                        TagsInput::make('tags')
                            ->suggestions(self::tagSuggestions(...))
                            ->nestedRecursiveRules(['max:'.MonitorTags::MaxLength])
                            ->visible(fn (Get $get): bool => self::is($get('scope'), ScheduledReportScope::Tags))
                            ->required(fn (Get $get): bool => self::is($get('scope'), ScheduledReportScope::Tags))
                            ->dehydrated(fn (Get $get): bool => self::is($get('scope'), ScheduledReportScope::Tags))
                            ->helperText('A monitor is included when it has any of these tags.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('schedule')
                    ->getStateUsing(fn (ScheduledReport $record): string => $record->scheduleLabel()),
                TextColumn::make('scope')->badge(),
                TextColumn::make('recipients')
                    ->label('To')
                    ->getStateUsing(fn (ScheduledReport $record): string => implode(', ', $record->recipients))
                    ->wrap(),
                TextColumn::make('next_run_at')->dateTime()->sortable(),
                TextColumn::make('last_sent_at')->dateTime()->placeholder('Never'),
                TextColumn::make('last_error')->placeholder('—')->limit(40)->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                self::sendNowAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function sendNowAction(): Action
    {
        return Action::make('sendNow')
            ->label('Send now')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalDescription('Email this report now. The next scheduled send stays the same.')
            ->action(function (ScheduledReport $record): void {
                $sent = SendScheduledReport::make()->handle($record, advanceSchedule: false);

                if ($sent->last_error !== null) {
                    Notification::make()
                        ->title('Report failed')
                        ->body($sent->last_error)
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Report sent')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduledReports::route('/'),
            'create' => CreateScheduledReport::route('/create'),
            'edit' => EditScheduledReport::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function weekdays(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    private static function is(mixed $state, ScheduledReportCadence|ScheduledReportScope $expected): bool
    {
        if ($state instanceof $expected) {
            return $state === $expected;
        }

        return $state === $expected->value;
    }

    /**
     * @return list<string>
     */
    private static function tagSuggestions(): array
    {
        return Monitor::query()
            ->pluck('tags')
            ->flatten()
            ->filter(fn (mixed $tag): bool => is_string($tag) && $tag !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
