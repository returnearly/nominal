<?php

declare(strict_types=1);

use App\Enums\NotificationChannelType;
use App\Support\NotificationChannelConfig;
use Illuminate\Validation\ValidationException;

it('maps aliases onto canonical keys and drops the rest', function () {
    expect(NotificationChannelConfig::normalize(NotificationChannelType::Slack, [
        'url' => ' https://hooks.slack.com/services/T/B/xxx ',
        'to' => 'ops@example.com',
        'noise' => 'nope',
    ]))->toBe([
        'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
    ]);

    expect(NotificationChannelConfig::normalize(NotificationChannelType::Webhook, [
        'webhook_url' => 'https://example.com/hooks/nominal',
    ]))->toBe([
        'url' => 'https://example.com/hooks/nominal',
    ]);

    expect(NotificationChannelConfig::normalize(NotificationChannelType::Pagerduty, [
        'integration_key' => ' R0123456789ABCDEF ',
    ]))->toBe([
        'routing_key' => 'R0123456789ABCDEF',
    ]);
});

it('copies aliases onto canonical keys for the form without dropping other values', function () {
    expect(NotificationChannelConfig::forForm(NotificationChannelType::Slack, [
        'url' => 'https://hooks.slack.com/services/T/B/xxx',
        'to' => 'ops@example.com',
    ]))->toMatchArray([
        'url' => 'https://hooks.slack.com/services/T/B/xxx',
        'webhook_url' => 'https://hooks.slack.com/services/T/B/xxx',
        'to' => 'ops@example.com',
    ]);
});

it('rejects a channel that is missing the fields its type needs', function () {
    NotificationChannelConfig::assertValid(NotificationChannelType::Mail, []);
})->throws(ValidationException::class);

it('rejects an invalid email or webhook URL', function (NotificationChannelType $type, array $config) {
    NotificationChannelConfig::assertValid($type, $config);
})->with([
    [NotificationChannelType::Mail, ['to' => 'not-an-email']],
    [NotificationChannelType::Slack, ['webhook_url' => 'not-a-url']],
])->throws(ValidationException::class);

it('accepts valid setup for each type', function (NotificationChannelType $type, array $config) {
    NotificationChannelConfig::assertValid($type, $config);

    expect(NotificationChannelConfig::normalize($type, $config))->toBe($config);
})->with([
    [NotificationChannelType::Mail, ['to' => 'ops@example.com']],
    [NotificationChannelType::Slack, ['webhook_url' => 'https://hooks.slack.com/services/T/B/xxx']],
    [NotificationChannelType::MicrosoftTeams, ['webhook_url' => 'https://outlook.office.com/webhook/abc']],
    [NotificationChannelType::Discord, ['webhook_url' => 'https://discord.com/api/webhooks/1/abc']],
    [NotificationChannelType::Webhook, ['url' => 'https://example.com/hooks/nominal']],
    [NotificationChannelType::Pagerduty, ['routing_key' => 'R0123456789ABCDEF']],
]);

it('summarizes destinations without leaking secrets', function () {
    expect(NotificationChannelConfig::destination(NotificationChannelType::Mail, [
        'to' => 'ops@example.com',
    ]))->toBe('ops@example.com');

    expect(NotificationChannelConfig::destination(NotificationChannelType::Mail, [
        'to' => 'ops@example.com',
        'host' => 'smtp.example.com',
    ]))->toBe('ops@example.com via smtp.example.com');

    expect(NotificationChannelConfig::destination(NotificationChannelType::Slack, [
        'webhook_url' => 'https://hooks.slack.com/services/T000/B000/secret',
    ]))->toBe('hooks.slack.com');

    expect(NotificationChannelConfig::destination(NotificationChannelType::Pagerduty, [
        'routing_key' => 'R0123456789ABCDEF',
    ]))->toBe('Routing key configured');
});

it('describes email setup without framework names', function () {
    $mail = NotificationChannelType::Mail;

    expect($mail->setupDescription())->toBe('Send alerts to one inbox. Set a mail server here, or leave the host blank to use the environment.')
        ->and($mail->field('to')?->helperText)->toBe('One address per channel.')
        ->and($mail->field('host')?->required)->toBeFalse()
        ->and($mail->field('host')?->helperText)->toBe('Leave blank to use the environment mail server.');
});

it('gives shared config keys the same input kind', function () {
    $kinds = [];

    foreach (NotificationChannelType::cases() as $type) {
        foreach ($type->fields() as $field) {
            if (isset($kinds[$field->key])) {
                expect($field->kind)->toBe($kinds[$field->key]);
            }

            $kinds[$field->key] = $field->kind;
        }
    }

    expect($kinds)->toHaveKeys(['to', 'host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name', 'webhook_url', 'url', 'routing_key']);
});

it('accepts a mail channel with only a recipient', function () {
    NotificationChannelConfig::assertValid(NotificationChannelType::Mail, [
        'to' => 'ops@example.com',
    ]);
});

it('accepts a mail channel with a mail server', function () {
    $config = [
        'to' => 'ops@example.com',
        'host' => 'smtp.example.com',
        'port' => '587',
        'username' => 'user',
        'password' => 'secret',
        'encryption' => 'tls',
        'from_address' => 'alerts@example.com',
        'from_name' => 'Nominal',
    ];

    NotificationChannelConfig::assertValid(NotificationChannelType::Mail, $config);

    expect(NotificationChannelConfig::normalize(NotificationChannelType::Mail, [
        ...$config,
        'port' => 587.0,
    ]))->toBe($config);
});

it('rejects an invalid mail server port or encryption', function (array $config) {
    NotificationChannelConfig::assertValid(NotificationChannelType::Mail, [
        'to' => 'ops@example.com',
        ...$config,
    ]);
})->with([
    [['port' => '0']],
    [['port' => '70000']],
    [['encryption' => 'starttls']],
])->throws(ValidationException::class);
