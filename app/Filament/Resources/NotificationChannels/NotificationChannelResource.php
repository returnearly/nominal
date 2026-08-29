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
use App\Support\NotificationChannelField;
use BackedEnum;
use Closure;
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
                ->columns(2)
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
     * @return list<Select|TextInput>
     */
    private static function setupFields(): array
    {
        $fields = [];

        foreach (NotificationChannelConfig::formKeys() as $key => $kind) {
            $fields[] = self::setupField($key, $kind);
        }

        return $fields;
    }

    private static function setupField(string $key, string $kind): Select|TextInput
    {
        $needs = fn (Get $get): bool => self::type($get)?->needs($key) === true;
        $required = fn (Get $get): bool => self::field($get, $key)?->required === true;

        if ($kind === 'select') {
            return self::applyShared(Select::make('config.'.$key)
                ->options(fn (Get $get): array => self::field($get, $key)?->options ?? [])
                ->placeholder(fn (Get $get): string => self::field($get, $key)?->placeholder ?: 'Automatic'), $key, $needs, $required);
        }

        $input = self::applyShared(TextInput::make('config.'.$key)
            ->placeholder(fn (Get $get): string => self::field($get, $key)?->placeholder ?? ''), $key, $needs, $required);

        return match ($kind) {
            'email' => $input->email($needs),
            'url' => $input->url($needs)->maxLength(fn (Get $get): int => self::field($get, $key)?->maxLength ?? 2048),
            'password' => $input->password($needs)->revealable($needs)->maxLength(fn (Get $get): int => self::field($get, $key)?->maxLength ?? 255),
            'integer' => $input->numeric()
                ->minValue(fn (Get $get): ?int => self::field($get, $key)?->min)
                ->maxValue(fn (Get $get): ?int => self::field($get, $key)?->max),
            default => $input->maxLength(fn (Get $get): int => self::field($get, $key)?->maxLength ?? 255),
        };
    }

    /**
     * @template T of Select|TextInput
     *
     * @param  T  $input
     * @param  Closure(Get): bool  $needs
     * @param  Closure(Get): bool  $required
     * @return T
     */
    private static function applyShared(Select|TextInput $input, string $key, Closure $needs, Closure $required): Select|TextInput
    {
        return $input
            ->label(fn (Get $get): string => self::field($get, $key)?->label ?? $key)
            ->helperText(fn (Get $get): ?string => self::field($get, $key)?->helperText)
            ->required($required)
            ->visible($needs)
            ->dehydrated($needs)
            ->columnSpan(fn (Get $get): int|string => (self::field($get, $key)?->wide ?? true) ? 'full' : 1);
    }

    private static function field(Get $get, string $key): ?NotificationChannelField
    {
        return self::type($get)?->field($key);
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
