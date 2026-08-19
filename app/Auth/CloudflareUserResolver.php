<?php

declare(strict_types=1);

namespace App\Auth;

use App\Actions\ProvisionCloudflareUser;
use App\Models\User;
use ReturnEarly\CloudflareZeroTrust\Contracts\ApplicationUserResolver;
use ReturnEarly\CloudflareZeroTrust\Principals\UserPrincipal;

final readonly class CloudflareUserResolver implements ApplicationUserResolver
{
    public function __construct(private ProvisionCloudflareUser $provisionCloudflareUser) {}

    public function resolve(UserPrincipal $principal): User
    {
        return $this->provisionCloudflareUser->handle($principal);
    }
}
