<?php

declare(strict_types=1);

namespace App\Support;

final class ReverbBrowser
{
    /**
     * @return array{enabled: bool, key: string|null, host: string, port: int, scheme: string}
     */
    public static function config(): array
    {
        $connection = config('broadcasting.connections.reverb');

        return [
            'enabled' => config('broadcasting.default') === 'reverb' && filled($connection['key'] ?? null),
            'key' => $connection['key'] ?? null,
            'host' => self::host(),
            'port' => self::port(),
            'scheme' => self::scheme(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function filamentEcho(): ?array
    {
        $config = self::config();

        if (! $config['enabled']) {
            return null;
        }

        return [
            'broadcaster' => 'reverb',
            'key' => $config['key'],
            'wsHost' => $config['host'],
            'wsPort' => $config['port'],
            'wssPort' => $config['port'],
            'authEndpoint' => '/broadcasting/auth',
            'auth' => [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            'disableStats' => true,
            'encrypted' => $config['scheme'] === 'https',
            'forceTLS' => $config['scheme'] === 'https',
            'enabledTransports' => ['ws', 'wss'],
        ];
    }

    private static function host(): string
    {
        $client = config('broadcasting.client.host');

        if (filled($client)) {
            return (string) $client;
        }

        $server = (string) (config('broadcasting.connections.reverb.options.host') ?: 'localhost');

        if (in_array($server, ['reverb', '0.0.0.0'], true)) {
            return request()->getHost() ?: 'localhost';
        }

        return $server;
    }

    private static function port(): int
    {
        $client = config('broadcasting.client.port');

        if (filled($client)) {
            return (int) $client;
        }

        return (int) (config('broadcasting.connections.reverb.options.port') ?: 8080);
    }

    private static function scheme(): string
    {
        $client = config('broadcasting.client.scheme');

        if (filled($client)) {
            return (string) $client;
        }

        return (string) (config('broadcasting.connections.reverb.options.scheme') ?: 'http');
    }
}
