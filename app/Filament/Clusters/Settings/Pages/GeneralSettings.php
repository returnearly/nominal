<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Settings\Pages;

use App\Enums\InterfaceAuth;
use App\Filament\Clusters\Settings\SettingsCluster;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class GeneralSettings extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'general';

    protected static ?string $title = 'General';

    protected static ?string $navigationLabel = 'General';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 1;

    public function content(Schema $schema): Schema
    {
        $auth = InterfaceAuth::current();

        return $schema->components([
            Section::make('Instance')
                ->description('These values come from environment configuration and cannot be changed here.')
                ->schema([
                    TextEntry::make('interface_auth')
                        ->label('Interface authentication')
                        ->state($auth->value),
                    TextEntry::make('operator_name')
                        ->label('Anonymous operator name')
                        ->state(fn (): string => (string) config('nominal.anonymous_operator.name'))
                        ->visible($auth === InterfaceAuth::None),
                    TextEntry::make('operator_email')
                        ->label('Anonymous operator email')
                        ->state(fn (): string => (string) config('nominal.anonymous_operator.email'))
                        ->visible($auth === InterfaceAuth::None),
                    TextEntry::make('probe_region')
                        ->label('Default probe region')
                        ->state(fn (): string => (string) config('nominal.probe_region')),
                    TextEntry::make('metrics_prefix')
                        ->label('Metrics prefix')
                        ->state(fn (): string => (string) config('nominal.metrics_prefix')),
                ]),
        ]);
    }
}
