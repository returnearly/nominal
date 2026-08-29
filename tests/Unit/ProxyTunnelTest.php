<?php

declare(strict_types=1);

use App\Checking\ProxyTunnel;
use App\Support\ProxyUrl;

function proxyPair(): array
{
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

    expect($pair)->toBeArray();

    stream_set_blocking($pair[0], true);
    stream_set_blocking($pair[1], true);

    return $pair;
}

function readUntil(mixed $stream, string $needle): string
{
    $raw = '';

    while (! str_contains($raw, $needle)) {
        $chunk = fread($stream, 1024);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $raw .= $chunk;
    }

    return $raw;
}

it('completes an HTTP CONNECT handshake', function () {
    [$client, $server] = proxyPair();
    fwrite($server, "HTTP/1.1 200 Connection Established\r\n\r\n");

    (new ProxyTunnel)->establish($client, ProxyUrl::parse('http://user:secret@proxy:8080'), 'example.com', 443);

    $request = readUntil($server, "\r\n\r\n");

    expect($request)
        ->toContain('CONNECT example.com:443 HTTP/1.1')
        ->toContain('Proxy-Authorization: Basic '.base64_encode('user:secret'));

    fclose($client);
    fclose($server);
});

it('fails when the HTTP proxy refuses CONNECT', function () {
    [$client, $server] = proxyPair();
    fwrite($server, "HTTP/1.1 403 Forbidden\r\n\r\n");

    expect(fn () => (new ProxyTunnel)->establish(
        $client,
        ProxyUrl::parse('http://proxy:8080'),
        'example.com',
        443,
    ))->toThrow(RuntimeException::class, 'HTTP proxy CONNECT failed');

    fclose($client);
    fclose($server);
});

it('completes a SOCKS5 handshake with remote DNS', function () {
    [$client, $server] = proxyPair();
    fwrite($server, "\x05\x00\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00");

    (new ProxyTunnel)->establish($client, ProxyUrl::parse('socks5h://127.0.0.1:1080'), 'example.com', 443);

    $greeting = fread($server, 3);
    $connect = readUntil($server, pack('n', 443));

    expect($greeting)->toBe("\x05\x01\x00")
        ->and($connect)->toStartWith("\x05\x01\x00\x03".chr(strlen('example.com')).'example.com');

    fclose($client);
    fclose($server);
});

it('authenticates to a SOCKS5 proxy', function () {
    [$client, $server] = proxyPair();
    fwrite($server, "\x05\x02\x01\x00\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00");

    (new ProxyTunnel)->establish(
        $client,
        ProxyUrl::parse('socks5://user:secret@127.0.0.1:1080'),
        '127.0.0.1',
        443,
    );

    $greeting = fread($server, 4);
    $auth = fread($server, 1 + 1 + 4 + 1 + 6);
    $connect = fread($server, 10);

    expect($greeting)->toBe("\x05\x02\x00\x02")
        ->and($auth)->toBe("\x01\x04user\x06secret")
        ->and($connect)->toBe("\x05\x01\x00\x01".inet_pton('127.0.0.1').pack('n', 443));

    fclose($client);
    fclose($server);
});

it('completes a SOCKS4a handshake for hostnames', function () {
    [$client, $server] = proxyPair();
    fwrite($server, "\x00\x5A\x00\x00\x00\x00\x00\x00");

    (new ProxyTunnel)->establish($client, ProxyUrl::parse('socks4a://127.0.0.1:1080'), 'example.com', 443);

    $request = readUntil($server, "example.com\0");

    expect($request)->toStartWith(pack('CCn', 4, 1, 443)."\x00\x00\x00\x01\0");

    fclose($client);
    fclose($server);
});
