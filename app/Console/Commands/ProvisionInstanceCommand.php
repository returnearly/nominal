<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ProvisionInstance;
use Illuminate\Console\Command;

final class ProvisionInstanceCommand extends Command
{
    protected $signature = 'nominal:provision';

    protected $description = 'Create the default probe and the first admin user';

    public function handle(ProvisionInstance $provision): int
    {
        $result = $provision->handle();

        $this->info("Default probe ready: {$result['region']} ({$result['queue']}).");

        if ($result['admin_email'] === null) {
            $this->comment('No admin user created. Set NOMINAL_ADMIN_EMAIL and NOMINAL_ADMIN_PASSWORD, then run nominal:provision again.');

            return self::SUCCESS;
        }

        if ($result['admin_created']) {
            $this->info("Created admin {$result['admin_email']}.");
            $this->line('Sign in with the NOMINAL_ADMIN_PASSWORD variable.');

            return self::SUCCESS;
        }

        $this->info("Admin already exists: {$result['admin_email']}.");

        return self::SUCCESS;
    }
}
