<?php

declare(strict_types=1);

use App\Actions\CheckUdp;
use App\Checking\SocketOutcome;
use App\Checking\UdpTransport;
use App\Enums\IpFamily;
use App\Models\Monitor;

it('passes UDP checks when the datagram is accepted', function () {
    $monitor = Monitor::factory()->udp()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(UdpTransport::class, new class implements UdpTransport
    {
        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, ?string $body = null): SocketOutcome
        {
            return SocketOutcome::ok(7, '1.1.1.1');
        }
    });

    $result = CheckUdp::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->latencyMs)->toBe(7);
});

it('fails UDP checks when the host cannot be reached', function () {
    $monitor = Monitor::factory()->udp()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(UdpTransport::class, new class implements UdpTransport
    {
        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, ?string $body = null): SocketOutcome
        {
            return SocketOutcome::failed(20, 'UDP response timed out');
        }
    });

    $result = CheckUdp::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('UDP response timed out');
});
