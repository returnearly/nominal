<?php

declare(strict_types=1);

use App\Checking\RdapThenWhoisDomainExpirationReader;
use App\Checking\WhoisClient;
use App\Checking\WhoisTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

function domainReader(array $responses, string $whois = ''): RdapThenWhoisDomainExpirationReader
{
    $stack = HandlerStack::create(new MockHandler($responses));

    return new RdapThenWhoisDomainExpirationReader(
        new WhoisClient(new class($whois) implements WhoisTransport
        {
            public function __construct(private string $whois) {}

            public function query(string $server, string $query, int $timeoutSeconds): string
            {
                return $this->whois;
            }
        }),
        new Client(['handler' => $stack]),
    );
}

function rdapBootstrap(): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'services' => [
            [
                ['com', 'net'],
                ['https://rdap.example/com/'],
            ],
        ],
    ], JSON_THROW_ON_ERROR));
}

it('reads domain expiration from RDAP', function () {
    Cache::flush();

    $expires = (new DateTimeImmutable('+400 days'))->format(DateTimeImmutable::ATOM);
    $reader = domainReader([
        rdapBootstrap(),
        new Response(200, ['Content-Type' => 'application/rdap+json'], json_encode([
            'events' => [
                ['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'],
                ['eventAction' => 'expiration', 'eventDate' => $expires],
            ],
        ], JSON_THROW_ON_ERROR)),
    ]);

    expect($reader->expiresAt('example.com')?->format(DateTimeImmutable::ATOM))->toBe($expires);
});

it('falls back to WHOIS when RDAP has no expiration event', function () {
    Cache::flush();

    $reader = domainReader([
        rdapBootstrap(),
        new Response(200, ['Content-Type' => 'application/rdap+json'], json_encode([
            'events' => [
                ['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'],
            ],
        ], JSON_THROW_ON_ERROR)),
    ], "Registry Expiry Date: 2029-02-02T00:00:00Z\n");

    expect($reader->expiresAt('example.com')?->format('Y-m-d'))->toBe('2029-02-02');
});

it('reuses a cached expiration instead of querying again', function () {
    Cache::flush();

    $expires = (new DateTimeImmutable('+400 days'))->format(DateTimeImmutable::ATOM);
    $reader = domainReader([
        rdapBootstrap(),
        new Response(200, ['Content-Type' => 'application/rdap+json'], json_encode([
            'events' => [
                ['eventAction' => 'expiration', 'eventDate' => $expires],
            ],
        ], JSON_THROW_ON_ERROR)),
        new Response(500),
    ]);

    expect($reader->expiresAt('example.com')?->format(DateTimeImmutable::ATOM))->toBe($expires)
        ->and($reader->expiresAt('example.com')?->format(DateTimeImmutable::ATOM))->toBe($expires);
});
