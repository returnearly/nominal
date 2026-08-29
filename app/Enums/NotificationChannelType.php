<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\NotificationChannelField;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NotificationChannelType: string implements HasColor, HasLabel
{
    case Mail = 'mail';
    case Slack = 'slack';
    case MicrosoftTeams = 'microsoft_teams';
    case Discord = 'discord';
    case Webhook = 'webhook';
    case Pagerduty = 'pagerduty';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Slack => 'Slack',
            self::MicrosoftTeams => 'Microsoft Teams',
            self::Discord => 'Discord',
            self::Webhook => 'Webhook',
            self::Pagerduty => 'PagerDuty',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Mail => 'info',
            self::Slack, self::Discord => 'purple',
            self::MicrosoftTeams => 'info',
            self::Webhook => 'gray',
            self::Pagerduty => 'warning',
        };
    }

    public function setupDescription(): string
    {
        return match ($this) {
            self::Mail => 'Send alerts to one inbox. Set a mail server here, or leave the host blank to use the environment.',
            self::Slack => 'Post alerts to a Slack channel with an incoming webhook.',
            self::MicrosoftTeams => 'Post alerts to a Teams channel with an incoming webhook.',
            self::Discord => 'Post alerts to a Discord channel with a webhook.',
            self::Webhook => 'POST JSON to your own endpoint when a monitor changes state.',
            self::Pagerduty => 'Open and resolve incidents with the PagerDuty Events API.',
        };
    }

    /**
     * @return list<NotificationChannelField>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Mail => [
                new NotificationChannelField(
                    key: 'to',
                    label: 'Recipient email',
                    kind: 'email',
                    placeholder: 'ops@example.com',
                    helperText: 'One address per channel.',
                ),
                new NotificationChannelField(
                    key: 'host',
                    label: 'Mail host',
                    kind: 'text',
                    placeholder: 'smtp.example.com',
                    helperText: 'Leave blank to use the environment mail server.',
                    required: false,
                    wide: false,
                ),
                new NotificationChannelField(
                    key: 'port',
                    label: 'Port',
                    kind: 'integer',
                    placeholder: '587',
                    helperText: 'Defaults to 587 when a host is set.',
                    required: false,
                    wide: false,
                    min: 1,
                    max: 65535,
                ),
                new NotificationChannelField(
                    key: 'username',
                    label: 'Username',
                    kind: 'text',
                    required: false,
                    wide: false,
                ),
                new NotificationChannelField(
                    key: 'password',
                    label: 'Password',
                    kind: 'password',
                    helperText: 'Leave blank if the server does not require authentication.',
                    required: false,
                    wide: false,
                ),
                new NotificationChannelField(
                    key: 'encryption',
                    label: 'Encryption',
                    kind: 'select',
                    placeholder: 'Automatic',
                    helperText: 'TLS is STARTTLS (typically 587). SSL is SMTPS (typically 465).',
                    required: false,
                    wide: false,
                    options: [
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                        'none' => 'None',
                    ],
                ),
                new NotificationChannelField(
                    key: 'from_address',
                    label: 'From address',
                    kind: 'email',
                    placeholder: 'alerts@example.com',
                    helperText: 'Leave blank to use the environment from address.',
                    required: false,
                    wide: false,
                ),
                new NotificationChannelField(
                    key: 'from_name',
                    label: 'From name',
                    kind: 'text',
                    placeholder: 'Nominal',
                    required: false,
                    wide: false,
                ),
            ],
            self::Slack => [
                new NotificationChannelField(
                    key: 'webhook_url',
                    label: 'Webhook URL',
                    kind: 'url',
                    placeholder: 'https://hooks.slack.com/services/…',
                    helperText: 'Incoming Webhooks app → Webhook URL. The webhook is bound to a channel.',
                    aliases: ['url'],
                    maxLength: 2048,
                ),
            ],
            self::MicrosoftTeams => [
                new NotificationChannelField(
                    key: 'webhook_url',
                    label: 'Webhook URL',
                    kind: 'url',
                    placeholder: 'https://outlook.office.com/webhook/…',
                    helperText: 'Channel → Connectors → Incoming Webhook, or a Workflows webhook URL.',
                    aliases: ['url'],
                    maxLength: 2048,
                ),
            ],
            self::Discord => [
                new NotificationChannelField(
                    key: 'webhook_url',
                    label: 'Webhook URL',
                    kind: 'url',
                    placeholder: 'https://discord.com/api/webhooks/…',
                    helperText: 'Channel settings → Integrations → Webhooks.',
                    aliases: ['url'],
                    maxLength: 2048,
                ),
            ],
            self::Webhook => [
                new NotificationChannelField(
                    key: 'url',
                    label: 'Endpoint URL',
                    kind: 'url',
                    placeholder: 'https://example.com/hooks/nominal',
                    helperText: 'Nominal POSTs JSON with event, headline, monitor, and result.',
                    aliases: ['webhook_url'],
                    maxLength: 2048,
                ),
            ],
            self::Pagerduty => [
                new NotificationChannelField(
                    key: 'routing_key',
                    label: 'Routing key',
                    kind: 'password',
                    helperText: 'Events API v2 integration key. Recoveries send a resolve for the monitor.',
                    aliases: ['integration_key'],
                ),
            ],
        };
    }

    public function field(string $key): ?NotificationChannelField
    {
        foreach ($this->fields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    public function needs(string $key): bool
    {
        return $this->field($key) !== null;
    }
}
