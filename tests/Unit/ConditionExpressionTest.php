<?php

declare(strict_types=1);

use App\Conditions\ConditionExpression;
use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\MonitorType;

it('parses status, body path, and function conditions', function () {
    expect(ConditionExpression::parse('[STATUS] == 200'))->toBe([
        'placeholder' => '[STATUS]',
        'path' => '',
        'comparator' => '==',
        'value' => '200',
    ])
        ->and(ConditionExpression::parse('[BODY].status == UP'))->toBe([
            'placeholder' => '[BODY]',
            'path' => '.status',
            'comparator' => '==',
            'value' => 'UP',
        ])
        ->and(ConditionExpression::parse('[RESPONSE_TIME] < 500'))->toBe([
            'placeholder' => '[RESPONSE_TIME]',
            'path' => '',
            'comparator' => '<',
            'value' => '500',
        ])
        ->and(ConditionExpression::parse('len([BODY].data) < 5'))->toBe([
            'placeholder' => 'len([BODY].data)',
            'path' => '',
            'comparator' => '<',
            'value' => '5',
        ]);
});

it('composes picker fields back into expressions', function () {
    expect(ConditionExpression::compose([
        'placeholder' => ConditionPlaceholder::Status->value,
        'comparator' => ConditionComparator::Equal->value,
        'value' => '200',
    ]))->toBe('[STATUS] == 200')
        ->and(ConditionExpression::compose([
            'placeholder' => ConditionPlaceholder::Body->value,
            'path' => 'status',
            'comparator' => ConditionComparator::Equal->value,
            'value' => 'UP',
        ]))->toBe('[BODY].status == UP')
        ->and(ConditionExpression::compose([
            'placeholder' => ConditionPlaceholder::Body->value,
            'path' => '.user.name',
            'comparator' => ConditionComparator::Equal->value,
            'value' => 'john.doe',
        ]))->toBe('[BODY].user.name == john.doe')
        ->and(ConditionExpression::compose([
            'placeholder' => ConditionPlaceholder::Status->value,
            'path' => '.ignored',
            'comparator' => ConditionComparator::LessThan->value,
            'value' => '300',
        ]))->toBe('[STATUS] < 300');
});

it('composes enum form values into an expression', function () {
    expect(ConditionExpression::compose([
        'placeholder' => ConditionPlaceholder::Status,
        'comparator' => ConditionComparator::Equal,
        'value' => 200,
    ]))->toBe('[STATUS] == 200');
});

it('round-trips expressions through parse and compose', function (string $expression) {
    expect(ConditionExpression::compose(ConditionExpression::parse($expression)))->toBe($expression);
})->with([
    '[STATUS] == 200',
    '[BODY].status == UP',
    '[CONNECTED] == true',
    '[CERTIFICATE_EXPIRATION] > 48h',
    '[STATUS] == any(200, 429)',
    'len([BODY].data) < 5',
    'has([BODY].errors) == false',
]);

it('returns type-specific default conditions', function () {
    expect(ConditionExpression::defaultExpressions(MonitorType::Http))->toBe([
        '[STATUS] >= 200',
        '[STATUS] <= 299',
    ])
        ->and(ConditionExpression::defaultExpressions(MonitorType::Ping))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and(ConditionExpression::defaultExpressions(MonitorType::Tcp))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and(ConditionExpression::defaultExpressions(MonitorType::Dns))->toBe([
            '[DNS_RCODE] == NOERROR',
        ])
        ->and(ConditionExpression::defaultExpressions(MonitorType::Tls))->toBe([
            '[CONNECTED] == true',
            '[CERTIFICATE_EXPIRATION] > 48h',
        ])
        ->and(ConditionExpression::defaultExpressions(MonitorType::Push))->toBe([
            '[CONNECTED] == true',
        ]);
});

it('limits ordering comparators to numeric placeholders', function () {
    expect(ConditionPlaceholder::Ip->comparators())->toBe([
        ConditionComparator::Equal,
        ConditionComparator::NotEqual,
    ])
        ->and(ConditionPlaceholder::Connected->comparators())->toBe([
            ConditionComparator::Equal,
            ConditionComparator::NotEqual,
        ])
        ->and(ConditionPlaceholder::DnsRcode->comparators())->toBe([
            ConditionComparator::Equal,
            ConditionComparator::NotEqual,
        ])
        ->and(ConditionPlaceholder::Status->comparators())->toBe(ConditionComparator::cases())
        ->and(ConditionPlaceholder::ResponseTime->comparators())->toBe(ConditionComparator::cases())
        ->and(ConditionPlaceholder::comparatorOptions(ConditionPlaceholder::Ip))->not->toHaveKey('>')
        ->and(ConditionPlaceholder::comparatorOptions(ConditionPlaceholder::Ip))->not->toHaveKey('<')
        ->and(ConditionPlaceholder::comparatorOptions(ConditionPlaceholder::Body))->toHaveKey('>=');
});
