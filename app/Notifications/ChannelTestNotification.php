<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NotificationChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ChannelTestNotification extends Notification implements NotificationChannelMessage
{
    public function __construct(
        public NotificationChannel $channel,
    ) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof NotificationChannel
            ? $notifiable->deliversVia()
            : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->headline())
            ->line($this->headline())
            ->line('Channel: '.$this->channel->name)
            ->line('If you received this, the email channel is working.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebhook(): array
    {
        return [
            'event' => 'test',
            'headline' => $this->headline(),
            'message' => $this->text(),
            'channel' => [
                'id' => $this->channel->id,
                'name' => $this->channel->name,
                'type' => $this->channel->type->value,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPagerDuty(): array
    {
        return [
            'event_action' => 'trigger',
            'dedup_key' => 'nominal-test-'.$this->channel->id,
            'payload' => [
                'summary' => $this->headline().': '.$this->channel->name,
                'source' => 'nominal',
                'severity' => 'info',
                'component' => 'notification-channel-test',
                'class' => $this->channel->type->value,
            ],
        ];
    }

    public function text(): string
    {
        return $this->headline().' ('.$this->channel->name.')';
    }

    public function headline(): string
    {
        return 'Nominal: test notification';
    }
}
