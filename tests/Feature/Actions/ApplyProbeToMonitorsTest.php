<?php

declare(strict_types=1);

use App\Actions\ApplyProbeToMonitors;
use App\Models\Monitor;
use App\Models\Probe;

it('attaches the probe to existing outbound monitors', function () {
    $http = Monitor::factory()->create();
    $ping = Monitor::factory()->ping()->create();
    $heartbeat = Monitor::factory()->heartbeat()->create();
    $probe = Probe::factory()->asDefault()->create();

    expect(ApplyProbeToMonitors::make()->handle($probe))->toBe(2)
        ->and($http->fresh()->probes()->where('probes.id', $probe->id)->exists())->toBeTrue()
        ->and($ping->fresh()->probes()->where('probes.id', $probe->id)->exists())->toBeTrue()
        ->and($heartbeat->fresh()->probes()->where('probes.id', $probe->id)->exists())->toBeFalse();
});

it('keeps probes that were already assigned', function () {
    $existing = Probe::factory()->create();
    $monitor = Monitor::factory()->create();
    $monitor->probes()->attach($existing);

    $probe = Probe::factory()->asDefault()->create();
    ApplyProbeToMonitors::make()->handle($probe);

    expect($monitor->fresh()->probes()->pluck('id')->all())
        ->toEqualCanonicalizing([$existing->id, $probe->id]);
});
