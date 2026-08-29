<?php

declare(strict_types=1);

use App\Conditions\CheckContext;
use App\Conditions\ConditionEvaluator;
use App\Conditions\InvalidConditionException;

function evaluate(string $expression, CheckContext $context): bool
{
    return (new ConditionEvaluator)->evaluate($expression, $context)->passed;
}

function httpContext(array $overrides = []): CheckContext
{
    $body = $overrides['body'] ?? ['status' => 'UP'];

    return new CheckContext(
        status: $overrides['status'] ?? 200,
        responseTimeMs: $overrides['responseTimeMs'] ?? 120,
        ip: $overrides['ip'] ?? '93.184.216.34',
        connected: $overrides['connected'] ?? true,
        certificateExpirationSeconds: $overrides['certificateExpirationSeconds'] ?? 86400 * 60,
        domainExpirationSeconds: $overrides['domainExpirationSeconds'] ?? 86400 * 400,
        body: $body,
        rawBody: is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        redirectUrl: $overrides['redirectUrl'] ?? null,
    );
}

it('evaluates Gatus-style status conditions', function () {
    expect(evaluate('[STATUS] == 200', httpContext()))->toBeTrue()
        ->and(evaluate('[STATUS] < 300', httpContext(['status' => 201])))->toBeTrue()
        ->and(evaluate('[STATUS] <= 299', httpContext(['status' => 299])))->toBeTrue()
        ->and(evaluate('[STATUS] > 400', httpContext(['status' => 403])))->toBeTrue()
        ->and(evaluate('[STATUS] == any(200, 429)', httpContext(['status' => 429])))->toBeTrue()
        ->and(evaluate('[STATUS] == 200', httpContext(['status' => 500])))->toBeFalse();
});

it('evaluates connected, response time, and IP', function () {
    expect(evaluate('[CONNECTED] == true', httpContext()))->toBeTrue()
        ->and(evaluate('[RESPONSE_TIME] < 500', httpContext()))->toBeTrue()
        ->and(evaluate('[RESPONSE_TIME] < 500', httpContext(['responseTimeMs' => 501])))->toBeFalse()
        ->and(evaluate('[IP] == 93.184.216.34', httpContext()))->toBeTrue()
        ->and(evaluate('[IP] == pat(93.184.*)', httpContext()))->toBeTrue();
});

it('evaluates JSON body paths, len, has, and pat', function () {
    $context = httpContext([
        'body' => [
            'user' => ['name' => 'john.doe'],
            'data' => [['id' => 1]],
            'age' => 1,
            'id' => 1,
        ],
    ]);

    expect(evaluate('[BODY].user.name == john.doe', $context))->toBeTrue()
        ->and(evaluate('[BODY].data[0].id == 1', $context))->toBeTrue()
        ->and(evaluate('[BODY].age == [BODY].id', $context))->toBeTrue()
        ->and(evaluate('len([BODY].data) < 5', $context))->toBeTrue()
        ->and(evaluate('len([BODY].user.name) == 8', $context))->toBeTrue()
        ->and(evaluate('has([BODY].errors) == false', $context))->toBeTrue()
        ->and(evaluate('has([BODY].user) == true', $context))->toBeTrue()
        ->and(evaluate('[BODY].user.name == pat(john*)', $context))->toBeTrue()
        ->and(evaluate('[BODY].data[0].id == any(1, 2)', $context))->toBeTrue();
});

it('matches a substring in the raw HTTP body', function () {
    $html = httpContext(['body' => '<h1>Welcome home</h1>']);
    $json = httpContext(['body' => ['status' => 'UP', 'message' => 'healthy']]);

    expect(evaluate('[BODY] == pat(*Welcome*)', $html))->toBeTrue()
        ->and(evaluate('[BODY] == pat(*goodbye*)', $html))->toBeFalse()
        ->and(evaluate('[BODY] == pat(*"status":"UP"*)', $json))->toBeTrue()
        ->and(evaluate('[BODY] == pat(*missing*)', $json))->toBeFalse();
});

it('evaluates redirect URLs and wildcard prefixes', function () {
    $context = httpContext(['redirectUrl' => 'https://example.com/app/home']);

    expect(evaluate('[REDIRECT] == https://example.com/app/home', $context))->toBeTrue()
        ->and(evaluate('[REDIRECT] == pat(https://example.com/app/*)', $context))->toBeTrue()
        ->and(evaluate('[REDIRECT] == pat(https://other.example.com/*)', $context))->toBeFalse()
        ->and(evaluate('[REDIRECT] == https://example.com/login', $context))->toBeFalse()
        ->and(evaluate('[REDIRECT] == https://example.com/app/home', httpContext()))->toBeFalse();
});

it('evaluates certificate expiration durations', function () {
    expect(evaluate('[CERTIFICATE_EXPIRATION] > 48h', httpContext([
        'certificateExpirationSeconds' => 49 * 3600,
    ])))->toBeTrue()
        ->and(evaluate('[CERTIFICATE_EXPIRATION] > 48h', httpContext([
            'certificateExpirationSeconds' => 3600,
        ])))->toBeFalse();
});

it('evaluates domain expiration durations', function () {
    expect(evaluate('[DOMAIN_EXPIRATION] > 720h', httpContext([
        'domainExpirationSeconds' => 721 * 3600,
    ])))->toBeTrue()
        ->and(evaluate('[DOMAIN_EXPIRATION] > 720h', httpContext([
            'domainExpirationSeconds' => 24 * 3600,
        ])))->toBeFalse();
});

it('evaluates DNS response codes and answer bodies', function () {
    $context = new CheckContext(
        connected: true,
        body: '93.184.216.34',
        rawBody: '93.184.216.34',
        dnsRcode: 'NOERROR',
    );

    expect(evaluate('[DNS_RCODE] == NOERROR', $context))->toBeTrue()
        ->and(evaluate('[DNS_RCODE] == NXDOMAIN', $context))->toBeFalse()
        ->and(evaluate('[BODY] == 93.184.216.34', $context))->toBeTrue();
});

it('treats inverted HTTP success as a normal condition', function () {
    expect(evaluate('[STATUS] == 403', httpContext(['status' => 403])))->toBeTrue()
        ->and(evaluate('[STATUS] == 403', httpContext(['status' => 200])))->toBeFalse();
});

it('fails missing JSON paths instead of throwing', function () {
    expect(evaluate('[BODY].missing == yes', httpContext()))->toBeFalse();
});

it('requires a comparator', function () {
    (new ConditionEvaluator)->evaluate('[STATUS]', httpContext());
})->throws(InvalidConditionException::class);

it('requires at least one condition to pass allPassed', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->allPassed([]))->toBeFalse()
        ->and($evaluator->allPassed($evaluator->evaluateAll(['[STATUS] == 200'], httpContext())))->toBeTrue();
});
