<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RollupCheckAggregates;
use Illuminate\Console\Command;

final class RollupCheckAggregatesCommand extends Command
{
    protected $signature = 'nominal:rollup-aggregates';

    protected $description = 'Roll hourly and daily check results into aggregates';

    public function handle(RollupCheckAggregates $action): int
    {
        $count = $action->handle();
        $this->info("Wrote {$count} aggregate rows.");

        return self::SUCCESS;
    }
}
