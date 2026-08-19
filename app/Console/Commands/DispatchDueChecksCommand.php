<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\DispatchDueChecks;
use Illuminate\Console\Command;

final class DispatchDueChecksCommand extends Command
{
    protected $signature = 'nominal:dispatch-due-checks';

    protected $description = 'Dispatch due Nominal monitors to their probe queues';

    public function handle(DispatchDueChecks $action): int
    {
        $count = $action->handle();
        $this->info("Dispatched {$count} checks.");

        return self::SUCCESS;
    }
}
