<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Checking\ProbeResult;
use App\Enums\AlertKind;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MonitorAlert extends Notification implements NotificationChannelMessage
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
        return $notifiable instanceof NotificationChannel
            ? $notifiable->deliversVia()
            : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->headline())
            ->line($this->headline())
            ->line('Monitor: '.$this->monitor->name)
            ->line('Target: '.$this->monitor->target)
            ->line('Status: '.$this->monitor->status->value)
            ->when($this->monitor->tags !== [], fn (MailMessage $mail): MailMessage => $mail->line('Tags: '.implode(', ', $this->monitor->tags)))
            ->when($this->monitor->description, fn (MailMessage $mail): MailMessage => $mail->line($this->monitor->description))
            ->when($this->result->message, fn (MailMessage $mail): MailMessage => $mail->line('Detail: '.$this->result->message));

        return $notifiable instanceof NotificationChannel
            ? $notifiable->configureMailMessage($mail)
            : $mail;
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

    /**
     * @return array<string, mixed>
     */
    public function toPagerDuty(): array
    {
        $summary = $this->headline().': '.$this->monitor->name;

        if (is_string($this->result->message) && $this->result->message !== '') {
            $summary .= ' — '.$this->result->message;
        }

        $details = [];

        if (is_string($this->result->message) && $this->result->message !== '') {
            $details['message'] = $this->result->message;
        }

        if ($this->result->httpStatus !== null) {
            $details['http_status'] = $this->result->httpStatus;
        }

        if ($this->result->latencyMs !== null) {
            $details['latency_ms'] = $this->result->latencyMs;
        }

        if (is_string($this->result->resolvedIp) && $this->result->resolvedIp !== '') {
            $details['ip'] = $this->result->resolvedIp;
        }

        if ($this->monitor->tags !== []) {
            $details['tags'] = implode(', ', $this->monitor->tags);
        }

        if (is_string($this->monitor->description) && $this->monitor->description !== '') {
            $details['description'] = $this->monitor->description;
        }

        $url = MonitorResource::getUrl('view', ['record' => $this->monitor]);

        return PagerDutyEvent::make(
            action: $this->kind === AlertKind::Recovered ? 'resolve' : 'trigger',
            dedupKey: (string) $this->monitor->id,
            summary: $summary,
            source: PagerDutyEvent::affectedSystem($this->monitor->displayTarget()),
            severity: $this->kind === AlertKind::Recovered ? 'info' : 'error',
            component: $this->monitor->name,
            group: $this->monitor->tags === [] ? null : implode(', ', $this->monitor->tags),
            class: $this->monitor->type->value,
            details: $details,
            clientUrl: filter_var($url, FILTER_VALIDATE_URL) ? $url : null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toPagerDutyEvents(): array
    {
        return [$this->toPagerDuty()];
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
