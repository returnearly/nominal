<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\PushSubscription;

interface SendsWebPush
{
    /**
     * @param  array{vapid_public_key: string, vapid_private_key: string, vapid_subject: string}  $vapid
     * @param  iterable<PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $payload
     * @return list<string> Subscription IDs that should be deleted (gone endpoints).
     */
    public function send(array $vapid, iterable $subscriptions, array $payload): array;
}
