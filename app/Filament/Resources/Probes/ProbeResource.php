<?php

declare(strict_types=1);

namespace App\Filament\Resources\Probes;

use App\Filament\Resources\Probes\Pages\CreateProbe;
use App\Filament\Resources\Probes\Pages\EditProbe;
use App\Filament\Resources\Probes\Pages\ListProbes;
use App\Models\Probe;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ProbeResource extends Resource
{
    protected static ?string $model = Probe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(64)->unique(ignoreRecord: true),
            TextInput::make('queue')->required()->maxLength(64)->helperText('Queue the worker listens to, e.g. checks.us-east'),
            Toggle::make('enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->badge(),
                TextColumn::make('queue'),
                IconColumn::make('enabled')->boolean(),
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => ListProbes::route('/'),
            'create' => CreateProbe::route('/create'),
            'edit' => EditProbe::route('/{record}/edit'),
        ];
    }
}
