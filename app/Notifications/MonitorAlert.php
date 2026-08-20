<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Checking\ProbeResult;
use App\Enums\AlertKind;
use App\Enums\NotificationChannelType;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Notifications\Channels\DiscordWebhookChannel;
use App\Notifications\Channels\GenericWebhookChannel;
use App\Notifications\Channels\MicrosoftTeamsChannel;
use App\Notifications\Channels\PagerDutyChannel;
use App\Notifications\Channels\SlackWebhookChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MonitorAlert extends Notification
{
    use Queueable;

    public function __construct(
        public Monitor $monitor,
        public ProbeResult $result,
        public AlertKind $kind,
    ) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof NotificationChannel) {
            return [];
        }

        return match ($notifiable->type) {
            NotificationChannelType::Mail => ['mail'],
            NotificationChannelType::Slack => [SlackWebhookChannel::class],
            NotificationChannelType::MicrosoftTeams => [MicrosoftTeamsChannel::class],
            NotificationChannelType::Discord => [DiscordWebhookChannel::class],
            NotificationChannelType::Webhook => [GenericWebhookChannel::class],
            NotificationChannelType::Pagerduty => [PagerDutyChannel::class],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->headline())
            ->line($this->headline())
            ->line('Monitor: '.$this->monitor->name)
            ->line('Target: '.$this->monitor->target)
            ->line('Status: '.$this->monitor->status->value)
            ->when($this->monitor->tags !== [], fn (MailMessage $mail): MailMessage => $mail->line('Tags: '.implode(', ', $this->monitor->tags)))
            ->when($this->monitor->description, fn (MailMessage $mail): MailMessage => $mail->line($this->monitor->description))
            ->when($this->result->message, fn (MailMessage $mail): MailMessage => $mail->line('Detail: '.$this->result->message));
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebhook(): array
    {
        return [
            'event' => $this->kind->value,
            'headline' => $this->headline(),
            'monitor' => [
                'id' => $this->monitor->id,
                'name' => $this->monitor->name,
                'description' => $this->monitor->description,
                'tags' => $this->monitor->tags,
                'type' => $this->monitor->type->value,
                'target' => $this->monitor->target,
                'status' => $this->monitor->status->value,
            ],
            'result' => [
                'success' => $this->result->success,
                'latency_ms' => $this->result->latencyMs,
                'http_status' => $this->result->httpStatus,
                'ip' => $this->result->resolvedIp,
                'message' => $this->result->message,
            ],
        ];
    }

    public function text(): string
    {
        $detail = $this->result->message ? " — {$this->result->message}" : '';
        $runbook = filled($this->monitor->description) ? "\n{$this->monitor->description}" : '';

        return $this->headline()." ({$this->monitor->name} → {$this->monitor->target}){$detail}{$runbook}";
    }

    public function headline(): string
    {
        return match ($this->kind) {
            AlertKind::Down => 'Nominal: monitor is down',
            AlertKind::Recovered => 'Nominal: monitor recovered',
            AlertKind::Reminder => 'Nominal: monitor is still down',
        };
    }
}
