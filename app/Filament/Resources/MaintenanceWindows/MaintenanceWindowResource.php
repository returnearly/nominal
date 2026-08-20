<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaintenanceWindows;

use App\Actions\EndMaintenanceWindow;
use App\Filament\Resources\MaintenanceWindows\Pages\CreateMaintenanceWindow;
use App\Filament\Resources\MaintenanceWindows\Pages\EditMaintenanceWindow;
use App\Filament\Resources\MaintenanceWindows\Pages\ListMaintenanceWindows;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class MaintenanceWindowResource extends Resource
{
    protected static ?string $model = MaintenanceWindow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?string $modelLabel = 'maintenance window';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'maintenance';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->default('Maintenance'),
                Toggle::make('applies_to_all')
                    ->label('All monitors')
                    ->live()
                    ->default(false),
                DateTimePicker::make('starts_at')
                    ->required()
                    ->seconds(false)
                    ->default(now()),
                DateTimePicker::make('ends_at')
                    ->seconds(false)
                    ->helperText('Leave empty to keep maintenance on until you end it.'),
                Textarea::make('message')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('monitor_ids')
                    ->label('Monitors')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->options(fn (): array => Monitor::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (Get $get): bool => ! (bool) $get('applies_to_all'))
                    ->required(fn (Get $get): bool => ! (bool) $get('applies_to_all'))
                    ->dehydrated(fn (Get $get): bool => ! (bool) $get('applies_to_all'))
                    ->columnSpanFull()
                    ->afterStateHydrated(function (Select $component, mixed $record): void {
                        if ($record instanceof MaintenanceWindow) {
                            $component->state($record->monitors()->pluck('id')->all());
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('phase')
                    ->badge()
                    ->getStateUsing(fn (MaintenanceWindow $record): string => $record->phase())
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'warning',
                        'scheduled' => 'info',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->dateTime()->placeholder('Until ended'),
                TextColumn::make('monitors_count')
                    ->label('Monitors')
                    ->counts('monitors')
                    ->formatStateUsing(fn (mixed $state, MaintenanceWindow $record): string => $record->applies_to_all
                        ? 'All'
                        : (string) $state),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordActions([
                Action::make('end')
                    ->label(fn (MaintenanceWindow $record): string => $record->isScheduled() ? 'Cancel' : 'End')
                    ->visible(fn (MaintenanceWindow $record): bool => $record->isActive() || $record->isScheduled())
                    ->requiresConfirmation()
                    ->action(fn (MaintenanceWindow $record) => EndMaintenanceWindow::make()->handle($record)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceWindows::route('/'),
            'create' => CreateMaintenanceWindow::route('/create'),
            'edit' => EditMaintenanceWindow::route('/{record}/edit'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof MaintenanceWindow
            && ($record->isActive() || $record->isScheduled());
    }
}
