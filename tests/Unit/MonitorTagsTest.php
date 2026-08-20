<?php

declare(strict_types=1);

use App\Support\MonitorTags;

it('trims, dedupes, and drops empty tags', function () {
    expect(MonitorTags::normalize([' Payments ', 'payments', '', 'prod', 'prod']))
        ->toBe(['Payments', 'prod']);
});

it('ignores non-string values and overlong tags', function () {
    expect(MonitorTags::normalize([
        ['nested'],
        null,
        12,
        str_repeat('a', MonitorTags::MaxLength + 1),
        'ok',
    ]))->toBe(['ok']);
});

it('caps the number of tags', function () {
    $tags = array_map(fn (int $i): string => "tag-{$i}", range(1, MonitorTags::MaxTags + 5));

    expect(MonitorTags::normalize($tags))->toHaveCount(MonitorTags::MaxTags);
});

it('returns an empty list for non-arrays', function () {
    expect(MonitorTags::normalize(null))->toBe([])
        ->and(MonitorTags::normalize('prod'))->toBe([]);
});
