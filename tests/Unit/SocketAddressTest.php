<?php

declare(strict_types=1);

use App\Checking\SocketAddress;

it('parses host, scheme, and IPv6 socket targets', function (string $target, string $host, int $port, ?string $scheme) {
    $address = SocketAddress::parse($target);

    expect($address->host)->toBe($host)
        ->and($address->port)->toBe($port)
        ->and($address->scheme)->toBe($scheme);
})->with([
    ['db.example.com:5432', 'db.example.com', 5432, null],
    ['tcp://db.example.com:5432', 'db.example.com', 5432, 'tcp'],
    ['127.0.0.1:3306', '127.0.0.1', 3306, null],
    ['tcp://[2001:db8::1]:6379', '2001:db8::1', 6379, 'tcp'],
    ['[2001:db8::1]:6379', '2001:db8::1', 6379, null],
]);

it('builds a stream remote for IPv4 and IPv6', function () {
    expect(SocketAddress::parse('db.example.com:5432')->remote('tcp'))->toBe('tcp://db.example.com:5432')
        ->and(SocketAddress::parse('[2001:db8::1]:6379')->remote('tcp'))->toBe('tcp://[2001:db8::1]:6379');
});

it('uses a default port when the target has none', function () {
    expect(SocketAddress::parse('example.com', 443)->port)->toBe(443);
});

it('rejects targets that cannot be parsed', function (string $target) {
    expect(fn () => SocketAddress::parse($target))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [''],
    ['example.com'],
    ['2001:db8::1'],
    ['example.com:0'],
    ['example.com:70000'],
    ['example.com:http'],
]);
