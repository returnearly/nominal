<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\ProvisionCloudflareUser;
use Closure;
use Illuminate\Http\Request;
use ReturnEarly\CloudflareZeroTrust\Principals\UserPrincipal;
use ReturnEarly\CloudflareZeroTrust\Support\Access;
use Symfony\Component\HttpFoundation\Response;

final readonly class LoginCloudflareInterfaceUser
{
    public function __construct(private ProvisionCloudflareUser $provisionCloudflareUser) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $principal = Access::user($request);

        abort_unless($principal instanceof UserPrincipal, 401);

        auth()->guard('web')->login($this->provisionCloudflareUser->handle($principal));

        return $next($request);
    }
}
