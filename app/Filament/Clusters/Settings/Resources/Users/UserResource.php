<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Settings\Resources\Users;

use App\Enums\InterfaceAuth;
use App\Filament\Clusters\Settings\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\Settings\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\Settings\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        $passwordUnused = InterfaceAuth::current() !== InterfaceAuth::Login;

        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->confirmed()
                ->helperText($passwordUnused
                    ? 'Password is unused while interface authentication is '.InterfaceAuth::current()->value.'.'
                    : null),
            TextInput::make('password_confirmation')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('created_at')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record->is(auth()->user())) {
            return Response::deny();
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
