<?php

declare(strict_types=1);

use App\Support\DownMonitorFavicon;

it('returns null when no monitors are down', function () {
    expect(DownMonitorFavicon::href(0))->toBeNull()
        ->and(DownMonitorFavicon::href(-1))->toBeNull();
});

it('renders a red favicon with the down count', function () {
    $svg = DownMonitorFavicon::svg(3);

    expect($svg)
        ->toContain('fill="'.DownMonitorFavicon::Color.'"')
        ->toContain('fill="'.DownMonitorFavicon::TextColor.'"')
        ->toContain('>3</text>')
        ->toContain('3 monitors down');

    $href = DownMonitorFavicon::href(3);

    expect($href)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($href, strlen('data:image/svg+xml;base64,'))))
        ->toBe($svg);
});

it('caps the favicon label at 99+', function () {
    expect(DownMonitorFavicon::svg(1))->toContain('>1</text>')
        ->and(DownMonitorFavicon::svg(1))->toContain('1 monitor down')
        ->and(DownMonitorFavicon::svg(12))->toContain('>12</text>')
        ->and(DownMonitorFavicon::svg(100))->toContain('>99+</text>')
        ->and(DownMonitorFavicon::svg(100))->toContain('99+ monitors down');
});
