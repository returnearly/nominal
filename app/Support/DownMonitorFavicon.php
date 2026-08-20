<?php

declare(strict_types=1);

namespace App\Support;

final class DownMonitorFavicon
{
    public const string Color = '#D15C5C';

    public const string TextColor = '#FDFFF8';

    public static function href(int $downCount): ?string
    {
        if ($downCount < 1) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($downCount));
    }

    public static function svg(int $downCount): string
    {
        $label = $downCount > 99 ? '99+' : (string) $downCount;
        $fontSize = match (strlen($label)) {
            1 => 38,
            2 => 30,
            default => 22,
        };
        $title = htmlspecialchars(
            $downCount === 1 ? '1 monitor down' : "{$label} monitors down",
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
        );
        $color = self::Color;
        $textColor = self::TextColor;

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" role="img" aria-label="{$title}">
          <title>{$title}</title>
          <rect width="64" height="64" rx="16" fill="{$color}"/>
          <text x="32" y="32" dy=".38em" text-anchor="middle" fill="{$textColor}" font-family="Inter, ui-sans-serif, system-ui, sans-serif" font-size="{$fontSize}" font-weight="700">{$label}</text>
        </svg>
        SVG;
    }
}
