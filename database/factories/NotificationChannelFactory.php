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
}
