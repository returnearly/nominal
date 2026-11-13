<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SubscribePushDevice implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array{endpoint: string, public_key: string, auth_token: string, content_encoding?: string, user_agent?: string|null}  $subscription
     */
    public function handle(NotificationChannel $channel, array $subscription, ?User $user = null): PushSubscription
    {
        if ($channel->type !== NotificationChannelType::Browser) {
            throw ValidationException::withMessages([
                'type' => 'Only browser channels accept push subscriptions.',
            ]);
        }

        $endpoint = trim($subscription['endpoint']);
        $publicKey = trim($subscription['public_key']);
        $authToken = trim($subscription['auth_token']);

        if ($endpoint === '' || $publicKey === '' || $authToken === '') {
            throw ValidationException::withMessages([
                'subscription' => 'A valid push subscription is required.',
            ]);
        }

        return PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
            [
                'notification_channel_id' => $channel->id,
                'user_id' => $user?->id,
                'endpoint' => $endpoint,
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => trim($subscription['content_encoding'] ?? '') ?: 'aes128gcm',
                'user_agent' => isset($subscription['user_agent']) && is_string($subscription['user_agent'])
                    ? mb_substr($subscription['user_agent'], 0, 255)
                    : null,
            ],
        );
    }
}
