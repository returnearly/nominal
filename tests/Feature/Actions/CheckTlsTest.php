<?php

declare(strict_types=1);

use App\Actions\CheckTls;
use App\Checking\SocketOutcome;
use App\Checking\TlsTransport;
use App\Enums\IpFamily;
use App\Models\Monitor;

it('passes TLS checks when the handshake succeeds and the cert is valid', function () {
    $monitor = Monitor::factory()->tls()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(TlsTransport::class, new class implements TlsTransport
    {
        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, bool $verifyTls, ?string $body = null, ?string $proxyUrl = null): SocketOutcome
        {
            return SocketOutcome::ok(15, '1.1.1.1', null, new DateTimeImmutable('+60 days'));
        }
    });

    $result = CheckTls::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->certificateExpiresAt)->not->toBeNull();
});

it('fails TLS checks when the handshake is refused', function () {
    $monitor = Monitor::factory()->tls()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(TlsTransport::class, new class implements TlsTransport
    {
        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, bool $verifyTls, ?string $body = null, ?string $proxyUrl = null): SocketOutcome
        {
            return SocketOutcome::failed(6, 'Connection refused');
        }
    });

    $result = CheckTls::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('Connection refused');
});

it('defaults TLS targets without a port to 443', function () {
    $monitor = Monitor::factory()->tls()->withDefaultConditions()->create([
        'target' => 'example.com',
    ]);
    $monitor->load('conditions');

    $captured = (object) ['port' => null];

    app()->instance(TlsTransport::class, new class($captured) implements TlsTransport
    {
        public function __construct(private readonly object $captured) {}

        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, bool $verifyTls, ?string $body = null, ?string $proxyUrl = null): SocketOutcome
        {
            $this->captured->port = $port;

            return SocketOutcome::ok(3, '1.1.1.1', null, new DateTimeImmutable('+60 days'));
        }
    });

    CheckTls::make()->handle($monitor);

    expect($captured->port)->toBe(443);
});

it('passes the monitor proxy to the TLS transport', function () {
    $monitor = Monitor::factory()->tls()->withDefaultConditions()->create([
        'proxy_url' => 'http://proxy.internal:3128',
    ]);
    $monitor->load('conditions');

    $captured = (object) ['proxy' => 'unset'];

    app()->instance(TlsTransport::class, new class($captured) implements TlsTransport
    {
        public function __construct(private readonly object $captured) {}

        public function connect(string $host, int $port, int $timeoutSeconds, IpFamily $family, bool $verifyTls, ?string $body = null, ?string $proxyUrl = null): SocketOutcome
        {
            $this->captured->proxy = $proxyUrl;

            return SocketOutcome::ok(3, '1.1.1.1', null, new DateTimeImmutable('+60 days'));
        }
    });

    CheckTls::make()->handle($monitor);

    expect($captured->proxy)->toBe('http://proxy.internal:3128');
});
