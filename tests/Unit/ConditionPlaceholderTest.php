<?php

declare(strict_types=1);

use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\MonitorType;

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
        ->and(ConditionPlaceholder::Redirect->comparators())->toBe([
            ConditionComparator::Equal,
            ConditionComparator::NotEqual,
        ])
        ->and(ConditionPlaceholder::Status->comparators())->toBe(ConditionComparator::cases())
        ->and(ConditionPlaceholder::ResponseTime->comparators())->toBe(ConditionComparator::cases())
        ->and(ConditionPlaceholder::comparatorOptions(ConditionPlaceholder::Ip))->not->toHaveKey('>')
        ->and(ConditionPlaceholder::comparatorOptions(ConditionPlaceholder::Ip))->not->toHaveKey('<')
        ->and(ConditionPlaceholder::comparatorOptions(ConditionPlaceholder::Body))->toHaveKey('>=');
});

it('limits condition placeholders to values the check type can produce', function () {
    expect(array_column(ConditionPlaceholder::forType(MonitorType::Http), 'value'))->toBe([
        '[STATUS]',
        '[BODY]',
        '[REDIRECT]',
        '[CONNECTED]',
        '[RESPONSE_TIME]',
        '[IP]',
        '[CERTIFICATE_EXPIRATION]',
        '[DOMAIN_EXPIRATION]',
    ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Ping), 'value'))->toBe([
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Tcp), 'value'))->toBe([
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[BODY]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Dns), 'value'))->toBe([
            '[DNS_RCODE]',
            '[BODY]',
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Tls), 'value'))->toBe([
            '[CONNECTED]',
            '[CERTIFICATE_EXPIRATION]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[BODY]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(ConditionPlaceholder::forType(MonitorType::Heartbeat))->toBe([])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Udp), 'value'))->toBe([
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[BODY]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::WebSocket), 'value'))->toBe([
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[BODY]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::GraphQL), 'value'))->toBe([
            '[STATUS]',
            '[BODY]',
            '[REDIRECT]',
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[CERTIFICATE_EXPIRATION]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Mysql), 'value'))->toBe([
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[BODY]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Redis), 'value'))->toBe([
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[BODY]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(array_column(ConditionPlaceholder::forType(MonitorType::Postgres), 'value'))->toBe([
            '[CONNECTED]',
            '[RESPONSE_TIME]',
            '[IP]',
            '[BODY]',
            '[DOMAIN_EXPIRATION]',
        ])
        ->and(ConditionPlaceholder::options(null, MonitorType::Ping))->not->toHaveKey('[STATUS]')
        ->and(ConditionPlaceholder::options(null, MonitorType::Ping))->not->toHaveKey('[DNS_RCODE]')
        ->and(ConditionPlaceholder::options(null, MonitorType::Ping))->not->toHaveKey('[REDIRECT]')
        ->and(ConditionPlaceholder::options('[STATUS]', MonitorType::Ping))->toHaveKey('[STATUS]');
});
