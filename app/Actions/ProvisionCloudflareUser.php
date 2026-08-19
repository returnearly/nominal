<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Str;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;
use ReturnEarly\CloudflareZeroTrust\Principals\UserPrincipal;

final readonly class ProvisionCloudflareUser implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(UserPrincipal $principal): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $principal->email()],
            [
                'name' => $this->displayName($principal),
                'password' => Str::password(32),
            ],
        );

        if ($user->wasRecentlyCreated) {
            $user->email_verified_at = now();
            $user->save();
        }

        return $user;
    }

    private function displayName(UserPrincipal $principal): string
    {
        $claimed = $principal->claims()['name'] ?? null;
        $claimed = is_string($claimed) ? trim($claimed) : '';

        if ($claimed !== '') {
            return $claimed;
        }

        return Str::headline(str_replace(['.', '_', '-'], ' ', Str::before($principal->email(), '@')));
    }
}
