<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannelType;
use App\Notifications\Channels\DiscordWebhookChannel;
use App\Notifications\Channels\GenericWebhookChannel;
use App\Notifications\Channels\MicrosoftTeamsChannel;
use App\Notifications\Channels\PagerDutyChannel;
use App\Notifications\Channels\SlackWebhookChannel;
use App\Notifications\Channels\SmtpMailChannel;
use App\Support\NotificationChannelConfig;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'type', 'config'])]
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'config' => AsEncryptedArrayObject::class,
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class)
            ->using(MonitorNotificationChannel::class)
            ->withPivot([
                'failure_threshold',
                'success_threshold',
                'send_on_resolved',
                'reminder_interval_seconds',
                'triggered',
                'last_notified_at',
            ]);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function destination(): Attribute
    {
        return Attribute::get(fn (): ?string => NotificationChannelConfig::destination(
            $this->type,
            $this->configArray(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function configArray(): array
    {
        return NotificationChannelConfig::from($this->config);
    }

    /**
     * @return array<string, string|int|null>|null
     */
    public function graphqlMail(): ?array
    {
        return $this->graphqlTypedConfig(NotificationChannelType::Mail);
    }

    /**
     * @return array<string, string>|null
     */
    public function graphqlSlack(): ?array
    {
        return $this->graphqlTypedConfig(NotificationChannelType::Slack);
    }

    /**
     * @return array<string, string>|null
     */
    public function graphqlMicrosoftTeams(): ?array
    {
        return $this->graphqlTypedConfig(NotificationChannelType::MicrosoftTeams);
    }

    /**
     * @return array<string, string>|null
     */
    public function graphqlDiscord(): ?array
    {
        return $this->graphqlTypedConfig(NotificationChannelType::Discord);
    }

    /**
     * @return array<string, string>|null
     */
    public function graphqlWebhook(): ?array
    {
        return $this->graphqlTypedConfig(NotificationChannelType::Webhook);
    }

    /**
     * @return array<string, string>|null
     */
    public function graphqlPagerduty(): ?array
    {
        return $this->graphqlTypedConfig(NotificationChannelType::Pagerduty);
    }

    /**
     * @return array<string, string|int|null>|null
     */
    private function graphqlTypedConfig(NotificationChannelType $type): ?array
    {
        if ($this->type !== $type) {
            return null;
        }

        $config = $this->configArray();
        $out = [];

        foreach ($type->fields() as $field) {
            $value = $config[$field->key] ?? null;

            if ($field->kind === 'integer') {
                if ($value === null || $value === '') {
                    if ($field->required) {
                        return null;
                    }

                    $out[Str::camel($field->key)] = null;

                    continue;
                }

                $out[Str::camel($field->key)] = (int) $value;

                continue;
            }

            if (! is_string($value) || $value === '') {
                if ($field->required) {
                    return null;
                }

                $out[Str::camel($field->key)] = null;

                continue;
            }

            $out[Str::camel($field->key)] = $value;
        }

        return $out;
    }

    public function configureMailMessage(MailMessage $mail): MailMessage
    {
        $from = $this->configArray()['from_address'] ?? null;

        if (! is_string($from) || $from === '') {
            return $mail;
        }

        $name = $this->configArray()['from_name'] ?? null;

        return $mail->from($from, is_string($name) && $name !== '' ? $name : null);
    }

    /**
     * @return list<string|class-string>
     */
    public function deliversVia(): array
    {
        return match ($this->type) {
            NotificationChannelType::Mail => [SmtpMailChannel::class],
            NotificationChannelType::Slack => [SlackWebhookChannel::class],
            NotificationChannelType::MicrosoftTeams => [MicrosoftTeamsChannel::class],
            NotificationChannelType::Discord => [DiscordWebhookChannel::class],
            NotificationChannelType::Webhook => [GenericWebhookChannel::class],
            NotificationChannelType::Pagerduty => [PagerDutyChannel::class],
        };
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->configArray()['to'] ?? null;
    }

    public function routeNotificationForSlack(): ?string
    {
        return $this->configArray()['webhook_url'] ?? null;
    }
}
