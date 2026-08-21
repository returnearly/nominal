<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('monitors', fn (User $user): bool => true, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('monitors.{monitorId}', fn (User $user, string $monitorId): bool => true, ['guards' => ['web', 'sanctum']]);

Broadcast::channel(
    User::class,
    fn (User $user, User $subscriber): bool => $user->is($subscriber),
    ['guards' => ['web', 'sanctum']],
);
