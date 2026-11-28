<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ProvisionInitialAdmin implements ActionsPatternInterface
{
    use ActionsPattern;

    private const string EMAIL = 'admin@nominal.local';

    /**
     * @return array{email: string, password: string}|null
     */
    public function handle(): ?array
    {
        if (User::query()->exists()) {
            return null;
        }

        $password = Str::password(24, symbols: false);

        try {
            User::query()->create([
                'name' => 'Nominal Admin',
                'email' => self::EMAIL,
                'password' => $password,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        return [
            'email' => self::EMAIL,
            'password' => $password,
        ];
    }
}
