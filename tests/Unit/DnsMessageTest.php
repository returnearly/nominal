<?php

declare(strict_types=1);

use App\Checking\DnsMessage;
use App\Enums\DnsQueryType;

it('encodes a DNS question and parses an A answer', function () {
    $query = DnsMessage::query('example.com', DnsQueryType::A, 0x1234);
    $question = substr($query, 12);
    $header = pack('nnnnnn', 0x1234, 0x8180, 1, 1, 0, 0);
    $answer = "\xc0\x0c".pack('nnNn', 1, 1, 60, 4).inet_pton('93.184.216.34');
    $parsed = DnsMessage::parse($header.$question.$answer);

    expect($parsed['rcode'])->toBe('NOERROR')
        ->and($parsed['answers'])->toBe(['93.184.216.34']);
});

it('parses NXDOMAIN responses without answers', function () {
    $query = DnsMessage::query('missing.example', DnsQueryType::A, 1);
    $question = substr($query, 12);
    $header = pack('nnnnnn', 1, 0x8183, 1, 0, 0, 0);

    expect(DnsMessage::parse($header.$question))->toBe([
        'rcode' => 'NXDOMAIN',
        'answers' => [],
    ]);
});

it('reverses IPv4 PTR query names', function () {
    expect(DnsMessage::queryName('1.2.3.4', DnsQueryType::PTR))->toBe('4.3.2.1.in-addr.arpa');
});
