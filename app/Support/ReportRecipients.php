<?php

declare(strict_types=1);

namespace App\Support;

final class ReportRecipients
{
    /**
     * @return list<string>
     */
    public static function normalize(mixed $recipients): array
    {
        if (! is_array($recipients)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }

            $recipient = mb_strtolower(trim($recipient));

            if ($recipient === '' || isset($seen[$recipient]) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $seen[$recipient] = true;
            $normalized[] = $recipient;
        }

        return $normalized;
    }

    public static function containsInvalid(mixed $recipients): bool
    {
        if (! is_array($recipients)) {
            return true;
        }

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                return true;
            }

            $recipient = trim($recipient);

            if ($recipient === '') {
                continue;
            }

            if (! filter_var(mb_strtolower($recipient), FILTER_VALIDATE_EMAIL)) {
                return true;
            }
        }

        return false;
    }
}
