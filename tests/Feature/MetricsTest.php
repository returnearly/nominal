<?php

declare(strict_types=1);

use App\Checking\ProbeResult;
use App\Metrics\MetricsStore;
use App\Models\Monitor;
use App\Models\Probe;

it('renders prometheus metrics for recorded checks', function () {
    $monitor = Monitor::factory()->create(['name' => 'api', 'group' => 'core']);
    $probe = Probe::factory()->create(['slug' => 'local']);

    app(MetricsStore::class)->record($monitor, $probe, new ProbeResult(
        success: true,
        connected: true,
        latencyMs: 33,
        httpStatus: 200,
        resolvedIp: '1.1.1.1',
        certificateExpiresAt: null,
        message: null,
        conditionResults: [],
    ));

    $response = test()->get('/metrics');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('nominal_results_total')
        ->toContain('monitor="api"')
        ->toContain('region="local"');
});
