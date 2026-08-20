<?php

declare(strict_types=1);

use App\Checking\WhoisClient;
use App\Checking\WhoisTransport;

function whoisClient(string $response): WhoisClient
{
    return new WhoisClient(new class($response) implements WhoisTransport
    {
        public function __construct(private string $response) {}

        public function query(string $server, string $query, int $timeoutSeconds): string
        {
            return $this->response;
        }
    });
}

it('parses common WHOIS expiration dates', function (string $hostname, string $text, string $expected) {
    expect(whoisClient($text)->parse($hostname, $text)?->format('Y-m-d'))->toBe($expected);
})->with([
    [
        'example.com',
        "Domain Name: EXAMPLE.COM\nRegistry Expiry Date: 2028-08-13T04:00:00Z\n",
        '2028-08-13',
    ],
    [
        'example.org',
        "Registrar Registration Expiration Date: 2027-01-15T00:00:00Z\n",
        '2027-01-15',
    ],
    [
        'example.uk',
        "Expiry date: 23-Nov-2026\n",
        '2026-11-23',
    ],
    [
        'example.ru',
        "paid-till: 2029-05-26T21:00:00Z\n",
        '2029-05-26',
    ],
]);

it('keeps the last parseable expiration date', function () {
    $text = "Registrar: Example Registrar\nExpiry Date: not-a-date\nRegistry Expiry Date: 2028-04-01T00:00:00Z\n";

    expect(whoisClient($text)->parse('example.com', $text)?->format('Y-m-d'))->toBe('2028-04-01');
});

it('does not query WHOIS for TLDs that omit expiration dates', function () {
    $queries = [];
    $client = new WhoisClient(new class($queries) implements WhoisTransport
    {
        public function __construct(private array &$queries) {}

        public function query(string $server, string $query, int $timeoutSeconds): string
        {
            $this->queries[] = [$server, $query];

            return '';
        }
    });

    expect($client->expirationDate('example.de'))->toBeNull()
        ->and($queries)->toBe([]);
});
