<?php

declare(strict_types=1);

use App\Checking\SocketOutcome;
use App\Checking\TcpChecker;
use App\Checking\TcpTransport;
use App\Enums\IpFamily;
use App\Models\Monitor;

it('passes TCP checks when the port accepts a connection', function () {
    $monitor = Monitor::factory()->tcp()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(TcpTransport::class, new class implements TcpTransport
    {
        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, ?string $body = null): SocketOutcome
        {
            return SocketOutcome::ok(12, '93.184.216.34');
        }
    });

    $result = app(TcpChecker::class)->check($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->latencyMs)->toBe(12)
        ->and($result->resolvedIp)->toBe('93.184.216.34');
});

it('fails TCP checks when the port is closed', function () {
    $monitor = Monitor::factory()->tcp()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(TcpTransport::class, new class implements TcpTransport
    {
        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, ?string $body = null): SocketOutcome
        {
            return SocketOutcome::failed(8, 'Connection refused');
        }
    });

    $result = app(TcpChecker::class)->check($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('Connection refused');
});

it('fails TCP checks when the target has no port', function () {
    $monitor = Monitor::factory()->tcp()->withDefaultConditions()->create([
        'target' => 'example.com',
    ]);
    $monitor->load('conditions');

    $result = app(TcpChecker::class)->check($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toContain('port');
});

it('writes an optional payload after connecting', function () {
    $monitor = Monitor::factory()->tcp()->withDefaultConditions()->create([
        'request_body' => 'PING',
    ]);
    $monitor->load('conditions');

    $captured = (object) ['body' => null];

    app()->instance(TcpTransport::class, new class($captured) implements TcpTransport
    {
        public function __construct(private readonly object $captured) {}

        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, ?string $body = null): SocketOutcome
        {
            $this->captured->body = $body;

            return SocketOutcome::ok(4, '127.0.0.1', '+PONG');
        }
    });

    $result = app(TcpChecker::class)->check($monitor);

    expect($captured->body)->toBe('PING')
        ->and($result->success)->toBeTrue()
        ->and($result->responseBody)->toBe('+PONG');
});
