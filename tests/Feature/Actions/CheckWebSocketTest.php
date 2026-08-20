<?php

declare(strict_types=1);

use App\Actions\CheckWebSocket;
use App\Checking\SocketOutcome;
use App\Checking\WebSocketTransport;
use App\Enums\IpFamily;
use App\Models\Monitor;

it('passes WebSocket checks when the upgrade succeeds', function () {
    $monitor = Monitor::factory()->websocket()->withDefaultConditions()->create();
    $monitor->load('conditions');

    $captured = (object) ['path' => null, 'secure' => null, 'body' => null];

    app()->instance(WebSocketTransport::class, new class($captured) implements WebSocketTransport
    {
        public function __construct(private readonly object $captured) {}

        public function connect(
            string $host,
            int $port,
            string $path,
            bool $secure,
            int $timeoutSeconds,
            IpFamily $family,
            bool $verifyTls,
            array $headers,
            ?string $body = null,
            ?string $proxyUrl = null,
        ): SocketOutcome {
            $this->captured->path = $path;
            $this->captured->secure = $secure;
            $this->captured->body = $body;

            return SocketOutcome::ok(22, '93.184.216.34', 'pong');
        }
    });

    $result = CheckWebSocket::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->responseBody)->toBe('pong')
        ->and($captured->path)->toBe('/socket')
        ->and($captured->secure)->toBeTrue()
        ->and($captured->body)->toBe('ping');
});

it('fails WebSocket checks when the upgrade is refused', function () {
    $monitor = Monitor::factory()->websocket()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(WebSocketTransport::class, new class implements WebSocketTransport
    {
        public function connect(
            string $host,
            int $port,
            string $path,
            bool $secure,
            int $timeoutSeconds,
            IpFamily $family,
            bool $verifyTls,
            array $headers,
            ?string $body = null,
            ?string $proxyUrl = null,
        ): SocketOutcome {
            return SocketOutcome::failed(9, 'WebSocket upgrade failed.');
        }
    });

    $result = CheckWebSocket::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toBe('WebSocket upgrade failed.');
});

it('passes the monitor proxy to the WebSocket transport', function () {
    $monitor = Monitor::factory()->websocket()->withDefaultConditions()->create([
        'proxy_url' => 'socks5h://proxy.internal:1080',
    ]);
    $monitor->load('conditions');

    $captured = (object) ['proxy' => 'unset'];

    app()->instance(WebSocketTransport::class, new class($captured) implements WebSocketTransport
    {
        public function __construct(private readonly object $captured) {}

        public function connect(
            string $host,
            int $port,
            string $path,
            bool $secure,
            int $timeoutSeconds,
            IpFamily $family,
            bool $verifyTls,
            array $headers,
            ?string $body = null,
            ?string $proxyUrl = null,
        ): SocketOutcome {
            $this->captured->proxy = $proxyUrl;

            return SocketOutcome::ok(5, '127.0.0.1');
        }
    });

    CheckWebSocket::make()->handle($monitor);

    expect($captured->proxy)->toBe('socks5h://proxy.internal:1080');
});
