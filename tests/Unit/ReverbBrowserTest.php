<?php

declare(strict_types=1);

use App\Support\ReverbBrowser;
use Illuminate\Http\Request;

it('is disabled when broadcasting is not reverb', function () {
    config(['broadcasting.default' => 'null']);

    expect(ReverbBrowser::config()['enabled'])->toBeFalse()
        ->and(ReverbBrowser::filamentEcho())->toBeNull();
});

it('uses the explicit client host and port', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'key',
        'broadcasting.client.host' => 'status.example',
        'broadcasting.client.port' => '9090',
        'broadcasting.client.scheme' => 'https',
    ]);

    expect(ReverbBrowser::config())->toMatchArray([
        'enabled' => true,
        'key' => 'key',
        'host' => 'status.example',
        'port' => 9090,
        'scheme' => 'https',
    ])->and(ReverbBrowser::filamentEcho())->toMatchArray([
        'broadcaster' => 'reverb',
        'key' => 'key',
        'wsHost' => 'status.example',
        'wsPort' => 9090,
        'wssPort' => 9090,
        'forceTLS' => true,
        'enabledTransports' => ['ws', 'wss'],
        'auth' => [
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ],
    ]);
});

it('falls back to the request host when reverb is a docker service name', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'key',
        'broadcasting.connections.reverb.options.host' => 'reverb',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.client.host' => null,
        'broadcasting.client.port' => null,
        'broadcasting.client.scheme' => null,
    ]);

    $this->app->instance('request', Request::create('http://admin.example/admin'));

    expect(ReverbBrowser::config())->toMatchArray([
        'host' => 'admin.example',
        'port' => 8080,
        'scheme' => 'http',
    ]);
});
