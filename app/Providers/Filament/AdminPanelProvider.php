<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Enums\InterfaceAuth;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Http\Middleware\AuthenticateAnonymousOperator;
use App\Http\Middleware\LoginCloudflareInterfaceUser;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ReturnEarly\CloudflareZeroTrust\Http\Middleware\AuthenticateCloudflareAccess;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $auth = InterfaceAuth::current();

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Nominal')
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->maxContentWidth(Width::Full)
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => '#5ADEB7',
                'success' => '#4FCBA6',
                'danger' => '#D15C5C',
                'warning' => '#DFC331',
                'info' => '#485FDE',
                'purple' => '#98A5EF',
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.meta')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.theme')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('components.monitor.stats-styles')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->homeUrl(fn (): string => MonitorResource::getUrl())
            ->topNavigation()
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware($this->panelMiddleware($auth))
            ->authMiddleware([
                Authenticate::class,
            ]);

        if ($auth === InterfaceAuth::Login) {
            $panel->login();
        }

        return $panel;
    }

    /**
     * @return list<string>
     */
    private function panelMiddleware(InterfaceAuth $auth): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ...($auth === InterfaceAuth::Login ? [AuthenticateSession::class] : []),
            ...match ($auth) {
                InterfaceAuth::None => [AuthenticateAnonymousOperator::class],
                InterfaceAuth::Login => [],
                InterfaceAuth::Cloudflare => [
                    AuthenticateCloudflareAccess::class.':admin',
                    LoginCloudflareInterfaceUser::class,
                ],
            },
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];
    }
}
