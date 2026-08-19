<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\PruneCheckResults;
use Illuminate\Console\Command;

final class PruneCheckResultsCommand extends Command
{
    protected $signature = 'nominal:prune-results';

    protected $description = 'Delete check results and aggregates older than each monitor retention window';

    public function handle(PruneCheckResults $action): int
    {
        $count = $action->handle();
        $this->info("Pruned {$count} check results.");

        return self::SUCCESS;
    }
}
