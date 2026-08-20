<?php

declare(strict_types=1);

use App\Actions\CheckPing;
use App\Checking\PingOutcome;
use App\Checking\PingTransport;
use App\Enums\IpFamily;
use App\Models\Monitor;

it('passes ping checks when the host is reachable', function () {
    $monitor = Monitor::factory()->ping()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(PingTransport::class, new class implements PingTransport
    {
        public function ping(string $host, int $timeoutSeconds, IpFamily $family): PingOutcome
        {
            return new PingOutcome(true, 18, '93.184.216.34', null);
        }
    });

    $result = CheckPing::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->latencyMs)->toBe(18)
        ->and($result->resolvedIp)->toBe('93.184.216.34');
});

it('fails ping checks when the host is unreachable', function () {
    $monitor = Monitor::factory()->ping()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(PingTransport::class, new class implements PingTransport
    {
        public function ping(string $host, int $timeoutSeconds, IpFamily $family): PingOutcome
        {
            return new PingOutcome(false, null, null, 'timed out');
        }
    });

    $result = CheckPing::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('timed out');
});
