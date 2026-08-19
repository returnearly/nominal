<?php

use App\Http\Middleware\AuthenticateAnonymousOperator;
use App\Http\Middleware\LoginCloudflareInterfaceUser;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use ReturnEarly\CloudflareZeroTrust\Http\Middleware\AuthenticateCloudflareAccess;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            $login = Filament::getLoginUrl();

            if ($login === null || $login === $request->fullUrl() || $login === $request->url()) {
                return null;
            }

            return $login;
        });

        $middleware
            ->prependToPriorityList(AuthenticatesRequests::class, AuthenticateCloudflareAccess::class)
            ->prependToPriorityList(AuthenticatesRequests::class, LoginCloudflareInterfaceUser::class)
            ->prependToPriorityList(AuthenticatesRequests::class, AuthenticateAnonymousOperator::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('graphql') || $request->expectsJson(),
        );
    })->create();
