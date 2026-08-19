<?php

declare(strict_types=1);

use App\Checking\HttpChecker;
use App\Checking\TlsCertificateReader;
use App\Models\Monitor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function fakeCertificates(?DateTimeImmutable $expiresAt = null): TlsCertificateReader
{
    return new class($expiresAt) implements TlsCertificateReader
    {
        public function __construct(private ?DateTimeImmutable $expiresAt) {}

        public function expiresAt(string $host, int $port, int $timeoutSeconds): ?DateTimeImmutable
        {
            return $this->expiresAt;
        }
    };
}

function httpChecker(array $responses, ?DateTimeImmutable $cert = null): HttpChecker
{
    app()->instance(TlsCertificateReader::class, fakeCertificates($cert));

    $client = new Client([
        'handler' => HandlerStack::create(new MockHandler($responses)),
    ]);

    return app(HttpChecker::class)->withClient($client);
}

it('passes HTTP checks when conditions match', function () {
    $monitor = Monitor::factory()->withDefaultConditions()->create([
        'target' => 'https://example.com/health',
        'request_headers' => ['X-Token' => 'secret'],
        'request_body' => '{"ping":true}',
        'method' => 'POST',
    ]);
    $monitor->load('conditions');

    $result = httpChecker([
        new Response(200, [], '{"status":"UP"}'),
    ], new DateTimeImmutable('+60 days'))->check($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->httpStatus)->toBe(200)
        ->and($result->connected)->toBeTrue();
});

it('fails HTTP checks when a condition fails', function () {
    $monitor = Monitor::factory()->withDefaultConditions()->create();
    $monitor->load('conditions');

    $result = httpChecker([
        new Response(500, [], 'nope'),
    ])->check($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->httpStatus)->toBe(500)
        ->and($result->message)->toContain('[STATUS] == 200');
});

it('sends a custom method and treats connection errors as failed conditions', function () {
    $monitor = Monitor::factory()->create();
    $monitor->conditions()->create(['expression' => '[CONNECTED] == true', 'sort' => 0]);
    $monitor->load('conditions');

    $result = httpChecker([
        new ConnectException(
            'Could not connect',
            new Request('GET', 'https://example.com/health'),
        ),
    ])->check($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse();
});
