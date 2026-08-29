<?php

declare(strict_types=1);

use App\Actions\ComposeConditionExpression;
use App\Actions\ParseConditionExpression;
use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;

it('parses status, body path, and function conditions', function () {
    $parse = ParseConditionExpression::make();

    expect($parse->handle('[STATUS] == 200'))->toBe([
        'placeholder' => '[STATUS]',
        'path' => '',
        'comparator' => '==',
        'value' => '200',
    ])
        ->and($parse->handle('[BODY].status == UP'))->toBe([
            'placeholder' => '[BODY]',
            'path' => '.status',
            'comparator' => '==',
            'value' => 'UP',
        ])
        ->and($parse->handle('[RESPONSE_TIME] < 500'))->toBe([
            'placeholder' => '[RESPONSE_TIME]',
            'path' => '',
            'comparator' => '<',
            'value' => '500',
        ])
        ->and($parse->handle('len([BODY].data) < 5'))->toBe([
            'placeholder' => 'len([BODY].data)',
            'path' => '',
            'comparator' => '<',
            'value' => '5',
        ]);
});

it('composes picker fields back into expressions', function () {
    $compose = ComposeConditionExpression::make();

    expect($compose->handle([
        'placeholder' => ConditionPlaceholder::Status->value,
        'comparator' => ConditionComparator::Equal->value,
        'value' => '200',
    ]))->toBe('[STATUS] == 200')
        ->and($compose->handle([
            'placeholder' => ConditionPlaceholder::Body->value,
            'path' => 'status',
            'comparator' => ConditionComparator::Equal->value,
            'value' => 'UP',
        ]))->toBe('[BODY].status == UP')
        ->and($compose->handle([
            'placeholder' => ConditionPlaceholder::Body->value,
            'path' => '.user.name',
            'comparator' => ConditionComparator::Equal->value,
            'value' => 'john.doe',
        ]))->toBe('[BODY].user.name == john.doe')
        ->and($compose->handle([
            'placeholder' => ConditionPlaceholder::Status->value,
            'path' => '.ignored',
            'comparator' => ConditionComparator::LessThan->value,
            'value' => '300',
        ]))->toBe('[STATUS] < 300');
});

it('composes enum form values into an expression', function () {
    expect(ComposeConditionExpression::make()->handle([
        'placeholder' => ConditionPlaceholder::Status,
        'comparator' => ConditionComparator::Equal,
        'value' => 200,
    ]))->toBe('[STATUS] == 200');
});

it('round-trips expressions through parse and compose', function (string $expression) {
    expect(ComposeConditionExpression::make()->handle(
        ParseConditionExpression::make()->handle($expression),
    ))->toBe($expression);
})->with([
    '[STATUS] == 200',
    '[BODY].status == UP',
    '[CONNECTED] == true',
    '[CERTIFICATE_EXPIRATION] > 48h',
    '[DOMAIN_EXPIRATION] > 720h',
    '[STATUS] == any(200, 429)',
    'len([BODY].data) < 5',
    'has([BODY].errors) == false',
    '[BODY] == pat(*healthy*)',
    '[REDIRECT] == pat(https://example.com/*)',
]);
