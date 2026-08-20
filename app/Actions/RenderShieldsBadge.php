<?php

declare(strict_types=1);

namespace App\Actions;

use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class RenderShieldsBadge implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(string $label, string $message, string $color): string
    {
        $labelWidth = $this->width($label);
        $messageWidth = $this->width($message);
        $width = $labelWidth + $messageWidth;
        $labelX = $labelWidth * 5;
        $messageX = ($labelWidth + $messageWidth / 2) * 10;
        $labelLength = max(0, ($labelWidth - 10) * 10);
        $messageLength = max(0, ($messageWidth - 10) * 10);
        $safeLabel = $this->escape($label);
        $safeMessage = $this->escape($message);
        $safeColor = $this->escape($color);
        $title = $this->escape($label.': '.$message);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="20" role="img" aria-label="{$title}">
          <title>{$title}</title>
          <linearGradient id="s" x2="0" y2="100%">
            <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
            <stop offset="1" stop-opacity=".1"/>
          </linearGradient>
          <clipPath id="r">
            <rect width="{$width}" height="20" rx="3" fill="#fff"/>
          </clipPath>
          <g clip-path="url(#r)">
            <rect width="{$labelWidth}" height="20" fill="#555"/>
            <rect x="{$labelWidth}" width="{$messageWidth}" height="20" fill="{$safeColor}"/>
            <rect width="{$width}" height="20" fill="url(#s)"/>
          </g>
          <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" text-rendering="geometricPrecision" font-size="110">
            <text aria-hidden="true" x="{$labelX}" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="{$labelLength}">{$safeLabel}</text>
            <text x="{$labelX}" y="140" transform="scale(.1)" fill="#fff" textLength="{$labelLength}">{$safeLabel}</text>
            <text aria-hidden="true" x="{$messageX}" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="{$messageLength}">{$safeMessage}</text>
            <text x="{$messageX}" y="140" transform="scale(.1)" fill="#fff" textLength="{$messageLength}">{$safeMessage}</text>
          </g>
        </svg>
        SVG;
    }

    private function width(string $text): int
    {
        return (int) ceil(mb_strlen($text) * 6.6) + 10;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
