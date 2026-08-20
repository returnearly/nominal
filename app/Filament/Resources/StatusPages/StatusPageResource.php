<?php

declare(strict_types=1);

namespace App\Filament\Resources\StatusPages;

use App\Enums\StatusPageTheme;
use App\Filament\Resources\StatusPages\Pages\CreateStatusPage;
use App\Filament\Resources\StatusPages\Pages\EditStatusPage;
use App\Filament\Resources\StatusPages\Pages\ListStatusPages;
use App\Filament\Resources\StatusPages\RelationManagers\IncidentsRelationManager;
use App\Models\StatusPage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class StatusPageResource extends Resource
{
    protected static ?string $model = StatusPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            if (filled($get('slug')) || ! is_string($state)) {
                                return;
                            }

                            $set('slug', Str::slug($state));
                        }),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(64)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    TextInput::make('headline')->maxLength(255),
                    Toggle::make('published')->default(false),
                    Textarea::make('description')->rows(3)->columnSpanFull(),
                    Toggle::make('show_targets')
                        ->label('Show monitor targets')
                        ->helperText('Off by default so internal URLs stay private.'),
                    TextInput::make('refresh_seconds')
                        ->numeric()
                        ->required()
                        ->default(30)
                        ->minValue(0)
                        ->helperText('Auto-refresh interval. Use 0 to disable.'),
                ]),
            Section::make('Branding')
                ->columns(2)
                ->components([
                    Select::make('theme')
                        ->options(StatusPageTheme::class)
                        ->default(StatusPageTheme::Dark)
                        ->required(),
                    TextInput::make('logo_url')->label('Logo URL')->maxLength(2048),
                    TextInput::make('favicon_url')->label('Favicon URL')->maxLength(2048),
                    TextInput::make('footer_text')->maxLength(255),
                    Textarea::make('custom_css')
                        ->label('Custom CSS')
                        ->rows(6)
                        ->columnSpanFull(),
                ]),
            Section::make('Publishing')
                ->columns(2)
                ->components([
                    TextInput::make('custom_domain')
                        ->label('Custom domain')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state): ?string => StatusPage::normalizeDomain($state))
                        ->helperText('CNAME this hostname to Nominal. The status page is served at / on that host.')
                        ->columnSpanFull(),
                    Toggle::make('password_protected')
                        ->label('Password protect')
                        ->dehydrated(false)
                        ->live()
                        ->afterStateHydrated(function (Toggle $component, mixed $record): void {
                            $component->state($record instanceof StatusPage && $record->isPasswordProtected());
                        }),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('password_protected'))
                        ->required(fn (Get $get, mixed $record): bool => (bool) $get('password_protected') && $record === null)
                        ->dehydrated(fn (Get $get, mixed $state): bool => (bool) $get('password_protected') && filled($state))
                        ->helperText('Leave blank to keep the current password.'),
                ]),
            Section::make('Monitors')
                ->components([
                    Repeater::make('listings')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Select::make('monitor_id')
                                ->label('Monitor')
                                ->relationship('monitor', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            TextInput::make('public_name')
                                ->label('Public name')
                                ->maxLength(255)
                                ->placeholder('Optional override'),
                        ])
                        ->columns(2)
                        ->orderColumn('sort')
                        ->reorderable()
                        ->addActionLabel('Add monitor')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->searchable(),
                IconColumn::make('published')->boolean(),
                TextColumn::make('custom_domain')->placeholder('—'),
                TextColumn::make('monitors_count')->counts('monitors')->label('Monitors'),
                TextColumn::make('updated_at')->since(),
            ])
            ->defaultSort('name')
            ->recordActions([
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
            IncidentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatusPages::route('/'),
            'create' => CreateStatusPage::route('/create'),
            'edit' => EditStatusPage::route('/{record}/edit'),
        ];
    }
}
