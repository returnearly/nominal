<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationChannels;

use App\Enums\NotificationChannelType;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\NotificationChannels\Pages\CreateNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\EditNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\ListNotificationChannels;
use App\Models\NotificationChannel;
use App\Support\NotificationChannelConfig;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Channel')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('type')
                        ->options(NotificationChannelType::class)
                        ->default(NotificationChannelType::Mail)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            $type = self::typeFrom($state);

                            if ($type === null) {
                                return;
                            }

                            $set('config', NotificationChannelConfig::forForm($type, $get('config') ?? []));
                        }),
                ]),
            Section::make('Setup')
                ->description(fn (Get $get): ?string => self::type($get)?->setupDescription())
                ->components(self::setupFields()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('destination')->placeholder('—'),
                TextColumn::make('updated_at')->since(),
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
            'index' => ListNotificationChannels::route('/'),
            'create' => CreateNotificationChannel::route('/create'),
            'edit' => EditNotificationChannel::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<TextInput>
     */
    private static function setupFields(): array
    {
        $fields = [];

        foreach (NotificationChannelConfig::formKeys() as $key => $kind) {
            $needs = fn (Get $get): bool => self::type($get)?->needs($key) === true;

            $input = TextInput::make('config.'.$key)
                ->label(fn (Get $get): string => self::type($get)?->field($key)?->label ?? $key)
                ->placeholder(fn (Get $get): string => self::type($get)?->field($key)?->placeholder ?? '')
                ->helperText(fn (Get $get): ?string => self::type($get)?->field($key)?->helperText)
                ->maxLength($kind === 'url' ? 2048 : 255)
                ->required($needs)
                ->visible($needs)
                ->dehydrated($needs)
                ->columnSpanFull();

            $fields[] = match ($kind) {
                'email' => $input->email($needs),
                'url' => $input->url($needs),
                'password' => $input->password($needs)->revealable($needs),
                default => $input,
            };
        }

        return $fields;
    }

    private static function type(Get $get): ?NotificationChannelType
    {
        return self::typeFrom($get('type'));
    }

    private static function typeFrom(mixed $type): ?NotificationChannelType
    {
        if ($type instanceof NotificationChannelType) {
            return $type;
        }

        if (! is_string($type) || $type === '') {
            return null;
        }

        return NotificationChannelType::tryFrom($type);
    }
}
