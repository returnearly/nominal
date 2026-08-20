<?php

declare(strict_types=1);

use App\Support\ProxyUrl;

it('parses HTTP and SOCKS proxy URLs', function (string $url, string $scheme, string $host, int $port, ?string $username, ?string $password) {
    $proxy = ProxyUrl::parse($url);

    expect($proxy->scheme)->toBe($scheme)
        ->and($proxy->host)->toBe($host)
        ->and($proxy->port)->toBe($port)
        ->and($proxy->username)->toBe($username)
        ->and($proxy->password)->toBe($password);
})->with([
    ['http://proxy.example:8080', 'http', 'proxy.example', 8080, null, null],
    ['https://proxy.example', 'https', 'proxy.example', 443, null, null],
    ['socks5://127.0.0.1:1080', 'socks5', '127.0.0.1', 1080, null, null],
    ['socks5h://user:p%40ss@[::1]:1080', 'socks5h', '::1', 1080, 'user', 'p@ss'],
    ['socks://proxy.example', 'socks5', 'proxy.example', 1080, null, null],
    ['socks4a://proxy.example:9050', 'socks4a', 'proxy.example', 9050, null, null],
]);

it('rejects unsupported proxy URLs', function (string $url) {
    expect(fn () => ProxyUrl::parse($url))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [''],
    ['not-a-url'],
    ['ftp://proxy.example:21'],
    ['http:///no-host'],
]);

it('builds a Guzzle proxy option from config', function () {
    config()->set('nominal.proxy', [
        'url' => null,
        'http' => 'http://http-proxy:8080',
        'https' => 'http://https-proxy:8080',
        'all' => null,
        'no' => 'localhost, 127.0.0.1',
    ]);

    expect(ProxyUrl::guzzleFromConfig())->toBe([
        'http' => 'http://http-proxy:8080',
        'https' => 'http://https-proxy:8080',
        'no' => ['localhost', '127.0.0.1'],
    ]);
});

it('uses ALL_PROXY when HTTP proxies are unset', function () {
    config()->set('nominal.proxy', [
        'url' => null,
        'http' => null,
        'https' => null,
        'all' => 'socks5h://127.0.0.1:1080',
        'no' => null,
    ]);

    expect(ProxyUrl::guzzleFromConfig())->toBe('socks5h://127.0.0.1:1080');
});

it('prefers NOMINAL_PROXY_URL over environment proxies', function () {
    config()->set('nominal.proxy', [
        'url' => 'socks5://corp-proxy:1080',
        'http' => 'http://http-proxy:8080',
        'https' => 'http://https-proxy:8080',
        'all' => null,
        'no' => null,
    ]);

    expect(ProxyUrl::guzzleFromConfig())->toBe('socks5://corp-proxy:1080');
});
