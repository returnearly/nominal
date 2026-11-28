<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ProvisionInitialAdmin;
use Illuminate\Console\Command;

final class EnsureAdminCommand extends Command
{
    protected $signature = 'nominal:ensure-admin';

    protected $description = 'Create the first admin login and print it once';

    public function handle(ProvisionInitialAdmin $provision): int
    {
        $login = $provision->handle();

        if ($login === null) {
            $this->line('Admin login already exists.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Nominal admin login');
        $this->line('Email: '.$login['email']);
        $this->line('Password: '.$login['password']);
        $this->newLine();

        return self::SUCCESS;
    }
}
