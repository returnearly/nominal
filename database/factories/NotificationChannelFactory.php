<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' alerts',
            'type' => NotificationChannelType::Webhook,
            'config' => [
                'url' => 'https://example.com/hooks/nominal',
            ],
        ];
    }

    public function mail(string $to = 'ops@example.com'): static
    {
        return $this->state([
            'type' => NotificationChannelType::Mail,
            'config' => ['to' => $to],
        ]);
    }

    public function slack(string $url = 'https://hooks.slack.com/services/T/B/xxx'): static
    {
        return $this->state([
            'type' => NotificationChannelType::Slack,
            'config' => ['webhook_url' => $url],
        ]);
    }
}
