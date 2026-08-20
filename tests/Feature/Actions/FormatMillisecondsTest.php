<?php

declare(strict_types=1);

use App\Actions\FormatMilliseconds;

it('keeps values under a second in milliseconds', function () {
    expect(FormatMilliseconds::make()->handle(0))->toBe('0ms')
        ->and(FormatMilliseconds::make()->handle(1))->toBe('1ms')
        ->and(FormatMilliseconds::make()->handle(999))->toBe('999ms');
});

it('converts values of a second or more to seconds', function () {
    expect(FormatMilliseconds::make()->handle(1000))->toBe('1s')
        ->and(FormatMilliseconds::make()->handle(1500))->toBe('1.5s')
        ->and(FormatMilliseconds::make()->handle(1234))->toBe('1.23s')
        ->and(FormatMilliseconds::make()->handle(10_000))->toBe('10s');
});

it('returns null when milliseconds are missing', function () {
    expect(FormatMilliseconds::make()->handle(null))->toBeNull();
});
