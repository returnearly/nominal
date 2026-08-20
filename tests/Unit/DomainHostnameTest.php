<?php

declare(strict_types=1);

use App\Checking\DomainHostname;
use App\Models\Monitor;

it('extracts a hostname from HTTP, socket, and ping targets', function (string $target, ?string $hostname) {
    expect(DomainHostname::fromTarget($target))->toBe($hostname);
})->with([
    ['https://example.com/health', 'example.com'],
    ['https://www.example.org:8443/status', 'www.example.org'],
    ['icmp://example.net', 'example.net'],
    ['example.com', 'example.com'],
    ['example.com:443', 'example.com'],
    ['tcp://db.example.com:5432', 'db.example.com'],
    ['wss://socket.example.com/ws', 'socket.example.com'],
    ['tls://shop.example.com:443', 'shop.example.com'],
    ['1.1.1.1', null],
    ['https://127.0.0.1/health', null],
    ['localhost', null],
    ['', null],
]);

it('reads the hostname from a monitor target', function () {
    $monitor = Monitor::factory()->make(['target' => 'https://status.example.com/health']);

    expect(DomainHostname::fromMonitor($monitor))->toBe('status.example.com');
});
