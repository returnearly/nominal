<?php

declare(strict_types=1);

namespace App\Support;

final class ApiManaged
{
    public static function enabled(): bool
    {
        return (bool) config('nominal.api_managed');
    }

    public static function message(): string
    {
        return 'Monitors and notification channels are managed through the API. Create, edit, and delete are turned off in the admin.';
    }

    public static function notice(): ?string
    {
        return self::enabled() ? self::message() : null;
    }

    public static function abortIfEnabled(): void
    {
        abort_if(self::enabled(), 403, self::message());
    }
}
