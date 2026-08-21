<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\InterfaceAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateBroadcastSubscriber
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (InterfaceAuth::current() === InterfaceAuth::None) {
            return app(AuthenticateAnonymousOperator::class)->handle($request, $next);
        }

        return $next($request);
    }
}
