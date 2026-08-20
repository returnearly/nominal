<?php

declare(strict_types=1);

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;

it('exposes 1h, 24h, 7d, and 30d uptime on a monitor', function () {
    $this->freezeTime();

    $user = User::factory()->create();
    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();

    CheckResult::factory()->count(9)->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => true,
        'checked_at' => now()->subMinutes(8),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $monitor->id,
        'probe_id' => $probe->id,
        'success' => false,
        'checked_at' => now()->subMinutes(2),
    ]);

    $uptime = graphql('
        query ($id: ID!) {
            monitor(id: $id) {
                uptime {
                    oneHour
                    twentyFourHours
                    sevenDays
                    thirtyDays
                }
            }
        }
    ', ['id' => $monitor->id], $user)->assertSuccessful()
        ->json('data.monitor.uptime');

    expect($uptime['oneHour'])->toEqual(90.0)
        ->and($uptime['twentyFourHours'])->toEqual(90.0)
        ->and($uptime['sevenDays'])->toEqual(90.0)
        ->and($uptime['thirtyDays'])->toEqual(90.0);
});
