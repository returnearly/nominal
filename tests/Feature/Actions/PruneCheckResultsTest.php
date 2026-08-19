<?php

declare(strict_types=1);

use App\Actions\PruneCheckResults;
use App\Models\Monitor;
use App\Models\Probe;

it('deletes check results older than the monitor retention window', function () {
    $monitor = Monitor::factory()->create(['retention_days' => 7]);
    $probe = Probe::factory()->create();

    $monitor->checkResults()->create([
        'probe_id' => $probe->id,
        'checked_at' => now()->subDays(8),
        'success' => true,
        'latency_ms' => 10,
    ]);

    $keep = $monitor->checkResults()->create([
        'probe_id' => $probe->id,
        'checked_at' => now()->subDays(2),
        'success' => true,
        'latency_ms' => 10,
    ]);

    PruneCheckResults::make()->handle();

    expect($monitor->checkResults()->count())->toBe(1)
        ->and($monitor->checkResults()->first()->id)->toBe($keep->id);
});
