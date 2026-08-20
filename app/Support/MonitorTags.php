<?php

declare(strict_types=1);

namespace App\Support;

final class MonitorTags
{
    public const int MaxTags = 32;

    public const int MaxLength = 64;

    /**
     * @return list<string>
     */
    public static function normalize(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $tag = trim($tag);

            if ($tag === '' || mb_strlen($tag) > self::MaxLength) {
                continue;
            }

            $key = mb_strtolower($tag);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $tag;

            if (count($normalized) === self::MaxTags) {
                break;
            }
        }

        return $normalized;
    }
}
