<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SendDueScheduledReports;
use Illuminate\Console\Command;

final class SendDueScheduledReportsCommand extends Command
{
    protected $signature = 'nominal:send-due-scheduled-reports';

    protected $description = 'Send scheduled reports that are due';

    public function handle(SendDueScheduledReports $action): int
    {
        $count = $action->handle();
        $this->info("Sent {$count} scheduled reports.");

        return self::SUCCESS;
    }
}
