<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

final class CreateApiTokenCommand extends Command
{
    protected $signature = 'nominal:token {email} {--name=terraform}';

    protected $description = 'Create an API token for GraphQL and Terraform';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user found for that email.');

            return self::FAILURE;
        }

        $this->line($user->createToken((string) $this->option('name'))->plainTextToken);

        return self::SUCCESS;
    }
}
