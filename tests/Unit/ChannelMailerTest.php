<?php

declare(strict_types=1);

use App\Support\ChannelMailer;

it('uses the environment mailer when the channel has no host', function () {
    expect(ChannelMailer::smtpConfig([
        'to' => 'ops@example.com',
        'username' => 'user',
        'password' => 'secret',
    ]))->toBeNull();
});

it('builds smtp settings from the channel config', function () {
    expect(ChannelMailer::smtpConfig([
        'to' => 'ops@example.com',
        'host' => 'smtp.example.com',
        'port' => '2525',
        'username' => 'user',
        'password' => 'secret',
        'encryption' => 'tls',
    ]))->toBe([
        'transport' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 2525,
        'username' => 'user',
        'password' => 'secret',
        'scheme' => 'smtp',
    ]);
});

it('defaults the port to 587 and infers the scheme', function (array $config, array $expected) {
    expect(ChannelMailer::smtpConfig([
        'host' => 'smtp.example.com',
        ...$config,
    ]))->toMatchArray($expected);
})->with([
    [[], ['port' => 587, 'scheme' => 'smtp']],
    [['port' => 465], ['port' => 465, 'scheme' => 'smtps']],
    [['encryption' => 'ssl'], ['scheme' => 'smtps']],
    [['encryption' => 'none'], ['scheme' => 'smtp', 'auto_tls' => false]],
]);
