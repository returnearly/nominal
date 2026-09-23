<?php

declare(strict_types=1);

namespace App\Notifications;

interface NotificationChannelMessage
{
    public function text(): string;

    /**
     * @return array<string, mixed>
     */
    public function toWebhook(): array;

    /**
     * @return array<string, mixed>
     */
    public function toPagerDuty(): array;

    /**
     * Events API v2 bodies, in send order. A test sends a trigger and then a resolve.
     *
     * @return list<array<string, mixed>>
     */
    public function toPagerDutyEvents(): array;
}
