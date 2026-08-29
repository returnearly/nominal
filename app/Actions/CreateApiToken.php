<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class CreateApiToken implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(User $user, string $name = 'terraform'): string
    {
        return $user->createToken($name)->plainTextToken;
    }
}
