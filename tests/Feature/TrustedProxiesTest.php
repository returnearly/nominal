<?php

declare(strict_types=1);

it('honors forwarded https when proxies are trusted', function () {
    config(['trustedproxy.proxies' => '*']);

    $this->get('/up', ['X-Forwarded-Proto' => 'https'])->assertOk();

    expect(request()->isSecure())->toBeTrue();
});

it('ignores forwarded https when no proxies are trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $this->get('/up', ['X-Forwarded-Proto' => 'https'])->assertOk();

    expect(request()->isSecure())->toBeFalse();
});
