<?php

declare(strict_types=1);

use App\Support\OutboundHttp;

it('sends webhook JSON through the configured proxy', function () {
    config()->set('nominal.proxy', [
        'url' => null,
        'http' => null,
        'https' => null,
        'all' => 'socks5h://127.0.0.1:1080',
        'no' => null,
    ]);

    expect(OutboundHttp::json()->getOptions()['proxy'] ?? null)->toBe('socks5h://127.0.0.1:1080');
});

it('leaves webhook clients unproxied when no proxy is configured', function () {
    config()->set('nominal.proxy', [
        'url' => null,
        'http' => null,
        'https' => null,
        'all' => null,
        'no' => null,
    ]);

    expect(OutboundHttp::json()->getOptions())->not->toHaveKey('proxy');
});
