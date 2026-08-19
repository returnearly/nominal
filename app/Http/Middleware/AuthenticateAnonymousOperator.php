<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateAnonymousOperator
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guest()) {
            auth()->login($this->operator());
        }

        return $next($request);
    }

    private function operator(): User
    {
        return User::query()->firstOrCreate(
            ['email' => (string) config('nominal.anonymous_operator.email')],
            [
                'name' => (string) config('nominal.anonymous_operator.name'),
                'password' => Str::password(32),
            ],
        );
    }
}
