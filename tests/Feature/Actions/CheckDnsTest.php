<?php

declare(strict_types=1);

use App\Actions\CheckDns;
use App\Checking\DnsOutcome;
use App\Checking\DnsTransport;
use App\Enums\DnsQueryType;
use App\Enums\IpFamily;
use App\Models\Monitor;

it('passes DNS checks when the rcode is NOERROR', function () {
    $monitor = Monitor::factory()->dns()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(DnsTransport::class, new class implements DnsTransport
    {
        public function query(string $resolver, string $name, DnsQueryType $type, int $timeoutSeconds, IpFamily $family): DnsOutcome
        {
            expect($resolver)->toBe('1.1.1.1')
                ->and($name)->toBe('example.com')
                ->and($type)->toBe(DnsQueryType::A);

            return DnsOutcome::ok(9, '1.1.1.1', 'NOERROR', ['93.184.216.34']);
        }
    });

    $result = CheckDns::make()->handle($monitor);

    expect($result->success)->toBeTrue()
        ->and($result->connected)->toBeTrue()
        ->and($result->responseBody)->toBe('93.184.216.34');
});

it('fails DNS checks when the rcode is NXDOMAIN', function () {
    $monitor = Monitor::factory()->dns()->withDefaultConditions()->create();
    $monitor->load('conditions');

    app()->instance(DnsTransport::class, new class implements DnsTransport
    {
        public function query(string $resolver, string $name, DnsQueryType $type, int $timeoutSeconds, IpFamily $family): DnsOutcome
        {
            return DnsOutcome::ok(11, '1.1.1.1', 'NXDOMAIN', []);
        }
    });

    $result = CheckDns::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeTrue()
        ->and($result->message)->toContain('[DNS_RCODE]');
});

it('fails DNS checks when the query name is missing', function () {
    $monitor = Monitor::factory()->dns()->withDefaultConditions()->create([
        'dns_query_name' => '',
    ]);
    $monitor->load('conditions');

    $result = CheckDns::make()->handle($monitor);

    expect($result->success)->toBeFalse()
        ->and($result->connected)->toBeFalse()
        ->and($result->message)->toContain('query name');
});
