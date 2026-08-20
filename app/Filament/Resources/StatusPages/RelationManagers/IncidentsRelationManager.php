<?php

declare(strict_types=1);

namespace App\Filament\Resources\StatusPages\RelationManagers;

use App\Enums\IncidentImpact;
use App\Enums\IncidentStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class IncidentsRelationManager extends RelationManager
{
    protected static string $relationship = 'incidents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Select::make('status')
                ->options(IncidentStatus::class)
                ->default(IncidentStatus::Investigating)
                ->required(),
            Select::make('impact')
                ->options(IncidentImpact::class)
                ->default(IncidentImpact::Minor)
                ->required(),
            DateTimePicker::make('started_at')
                ->default(now())
                ->required(),
            DateTimePicker::make('resolved_at'),
            Select::make('monitors')
                ->relationship('monitors', 'name')
                ->multiple()
                ->preload()
                ->columnSpanFull(),
            Repeater::make('updates')
                ->relationship()
                ->schema([
                    Select::make('status')
                        ->options(IncidentStatus::class)
                        ->required()
                        ->default(IncidentStatus::Investigating),
                    DateTimePicker::make('posted_at')->default(now())->required(),
                    Textarea::make('message')->required()->rows(3)->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->addActionLabel('Add update')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('impact')->badge(),
                TextColumn::make('started_at')->since(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
