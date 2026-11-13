<?php

declare(strict_types=1);

namespace App\Support;

use Minishlink\WebPush\VAPID;
use Throwable;

final class WebPushVapid
{
    /**
     * @return array{vapid_public_key: string, vapid_private_key: string, vapid_subject: string}
     */
    public static function generate(?string $subject = null): array
    {
        $keys = VAPID::createVapidKeys();

        return [
            'vapid_public_key' => $keys['publicKey'],
            'vapid_private_key' => $keys['privateKey'],
            'vapid_subject' => $subject ?? self::defaultSubject(),
        ];
    }

    public static function defaultSubject(): string
    {
        $url = (string) config('app.url', 'http://localhost');
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'mailto:noreply@localhost';
        }

        return 'mailto:noreply@'.$host;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{vapid_public_key: string, vapid_private_key: string, vapid_subject: string}|null
     */
    public static function fromConfig(array $config): ?array
    {
        $public = $config['vapid_public_key'] ?? null;
        $private = $config['vapid_private_key'] ?? null;
        $subject = $config['vapid_subject'] ?? null;

        if (! is_string($public) || $public === '' || ! is_string($private) || $private === '') {
            return null;
        }

        return [
            'vapid_public_key' => $public,
            'vapid_private_key' => $private,
            'vapid_subject' => is_string($subject) && $subject !== '' ? $subject : self::defaultSubject(),
        ];
    }

    public static function available(): bool
    {
        try {
            self::generate();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
