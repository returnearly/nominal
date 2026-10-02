<?php

declare(strict_types=1);

it('describes a nominal railway template with one generated app key', function () {
    $template = json_decode(
        (string) file_get_contents(base_path('railway/template.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $services = collect($template['services'])->keyBy('name');

    expect($services->keys()->sort()->values()->all())
        ->toBe(['Postgres', 'migrate', 'scheduler', 'web', 'worker']);

    expect($services['web']['source']['image'])->toBe('ghcr.io/returnearly/nominal:latest')
        ->and($services['web']['deploy']['healthcheckPath'])->toBe('/up')
        ->and($services['web']['deploy']['startCommand'])->toContain('octane:start')
        ->and($services['web']['networking']['serviceDomains']['<hasDomain>:8080']['port'])->toBe(8080)
        ->and($services['web']['variables']['APP_KEY']['defaultValue'])->toStartWith('base64:${{secret(')
        ->and($services['web']['variables']['NOMINAL_ADMIN_PASSWORD']['defaultValue'])->toContain('${{secret(')
        ->and($services['web']['variables']['DB_URL']['defaultValue'])->toBe('${{Postgres.DATABASE_URL}}')
        ->and($services['web']['variables']['TRUSTED_PROXIES']['defaultValue'])->toBe('*');

    foreach (['worker', 'scheduler', 'migrate'] as $name) {
        expect($services[$name]['source']['image'])->toBe('ghcr.io/returnearly/nominal:latest')
            ->and($services[$name]['variables']['APP_KEY']['defaultValue'])->toBe('${{web.APP_KEY}}')
            ->and($services[$name]['variables']['NOMINAL_ADMIN_PASSWORD']['defaultValue'])->toBe('${{web.NOMINAL_ADMIN_PASSWORD}}')
            ->and($services[$name]['variables']['DB_URL']['defaultValue'])->toBe('${{Postgres.DATABASE_URL}}');
    }

    expect($services['worker']['deploy']['startCommand'])->toContain('queue:work')
        ->and($services['scheduler']['deploy']['startCommand'])->toBe('php artisan schedule:work')
        ->and($services['migrate']['deploy']['startCommand'])->toBe('sh railway/migrate.sh')
        ->and($services['migrate']['deploy']['restartPolicyType'])->toBe('ON_FAILURE')
        ->and($services['Postgres']['source']['image'])->toBe('ghcr.io/railwayapp-templates/postgres-ssl:18')
        ->and($services['Postgres']['variables']['POSTGRES_PASSWORD']['defaultValue'])->toContain('${{ secret(')
        ->and($services['Postgres']['volumeMounts'])->not->toBeEmpty();

    $script = (string) file_get_contents(base_path('railway/migrate.sh'));

    expect($script)->toContain('php artisan migrate --force')
        ->and($script)->toContain('php artisan nominal:provision');
});
