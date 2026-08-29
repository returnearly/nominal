<?php

declare(strict_types=1);

use App\Actions\DetectDomainExpirationCondition;

it('detects domain expiration placeholders in expressions', function () {
    $detect = DetectDomainExpirationCondition::make();

    expect($detect->handle(['[STATUS] == 200']))->toBeFalse()
        ->and($detect->handle(['[DOMAIN_EXPIRATION] > 720h']))->toBeTrue()
        ->and($detect->handle(null))->toBeFalse();
});
