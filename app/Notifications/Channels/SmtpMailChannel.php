<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Support\ChannelMailer;
use App\Support\ChannelMailerFactory;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

final class SmtpMailChannel
{
    public function __construct(
        private MailChannel $mail,
        private MailManager $manager,
        private Markdown $markdown,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $this->channel($notifiable)->send($notifiable, $notification);
    }

    private function channel(object $notifiable): MailChannel
    {
        if (! $notifiable instanceof NotificationChannel) {
            return $this->mail;
        }

        $smtp = ChannelMailer::smtpConfig($notifiable->configArray());

        if ($smtp === null) {
            return $this->mail;
        }

        return new MailChannel(
            new ChannelMailerFactory($this->manager->build($smtp)),
            $this->markdown,
        );
    }
}
