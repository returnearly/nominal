<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        $endpoint = 'https://push.example.com/s/'.fake()->uuid();

        return [
            'notification_channel_id' => NotificationChannel::factory()->browser(),
            'user_id' => null,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => fake()->sha256(),
            'auth_token' => fake()->sha1(),
            'content_encoding' => 'aes128gcm',
            'user_agent' => 'Mozilla/5.0',
        ];
    }
}
