<?php

declare(strict_types=1);

use App\Actions\DefaultConditionExpressions;
use App\Enums\MonitorType;

it('returns type-specific default conditions', function () {
    $defaults = DefaultConditionExpressions::make();

    expect($defaults->handle(MonitorType::Http))->toBe([
        '[STATUS] >= 200',
        '[STATUS] <= 299',
    ])
        ->and($defaults->handle(MonitorType::Ping))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and($defaults->handle(MonitorType::Tcp))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and($defaults->handle(MonitorType::Dns))->toBe([
            '[DNS_RCODE] == NOERROR',
        ])
        ->and($defaults->handle(MonitorType::Tls))->toBe([
            '[CONNECTED] == true',
            '[CERTIFICATE_EXPIRATION] > 48h',
        ])
        ->and($defaults->handle(MonitorType::Heartbeat))->toBe([])
        ->and($defaults->handle(MonitorType::Udp))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and($defaults->handle(MonitorType::WebSocket))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and($defaults->handle(MonitorType::GraphQL))->toBe([
            '[STATUS] >= 200',
            '[STATUS] <= 299',
        ])
        ->and($defaults->handle(MonitorType::Mysql))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and($defaults->handle(MonitorType::Redis))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ])
        ->and($defaults->handle(MonitorType::Postgres))->toBe([
            '[CONNECTED] == true',
            '[RESPONSE_TIME] < 50',
        ]);
});
