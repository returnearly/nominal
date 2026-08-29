<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Settings\Pages;

use App\Actions\CreateApiToken;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Js;
use Laravel\Sanctum\PersonalAccessToken;

final class ManageApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'api-tokens';

    protected static ?string $title = 'API Tokens';

    protected static ?string $navigationLabel = 'API Tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 5;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PersonalAccessToken::query()->with('tokenable'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('tokenable.email')->label('User'),
                TextColumn::make('last_used_at')->since()->placeholder('Never'),
                TextColumn::make('created_at')->since(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->modalHeading('Revoke API token')
                    ->successNotificationTitle('Token revoked'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create token')
                ->schema([
                    Select::make('user_id')
                        ->label('User')
                        ->options(fn (): array => User::query()->orderBy('email')->pluck('email', 'id')->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->default('terraform'),
                ])
                ->action(function (array $data): void {
                    $user = User::query()->findOrFail($data['user_id']);
                    $plain = CreateApiToken::make()->handle($user, (string) $data['name']);

                    Notification::make()
                        ->title('API token created')
                        ->body($plain)
                        ->success()
                        ->persistent()
                        ->actions([
                            Action::make('copy')
                                ->label('Copy')
                                ->alpineClickHandler('window.navigator.clipboard.writeText('.Js::from($plain).')'),
                        ])
                        ->send();
                }),
        ];
    }
}
