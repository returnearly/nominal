<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('monitors', fn (User $user): bool => true);

Broadcast::channel('monitors.{monitorId}', fn (User $user, string $monitorId): bool => true);
