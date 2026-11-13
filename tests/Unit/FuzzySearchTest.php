<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Support\FuzzySearch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tokenizes by whitespace and drops empties', function () {
    expect(FuzzySearch::tokens('  API   gateway  '))->toBe(['API', 'gateway'])
        ->and(FuzzySearch::tokens(''))->toBe([])
        ->and(FuzzySearch::tokens('   '))->toBe([]);
});

it('escapes like wildcards in patterns', function () {
    expect(FuzzySearch::patterns('100%_up'))->toContain('%100\%\_up%');
});

it('adds single-character typo patterns for tokens of length 4 or more', function () {
    $patterns = FuzzySearch::patterns('gateway');

    expect($patterns)->toContain('%gateway%')
        ->and($patterns)->toContain('%_ateway%')
        ->and($patterns)->toContain('%g_teway%')
        ->and($patterns)->toContain('%gatewa_%');
});

it('does not add typo patterns for short tokens', function () {
    expect(FuzzySearch::patterns('api'))->toBe(['%api%']);
});

it('matches monitors case-insensitively across columns', function () {
    $hit = Monitor::factory()->create([
        'name' => 'Payments API',
        'target' => 'https://pay.example/health',
        'description' => 'Owned by Billing',
        'tags' => ['Prod'],
    ]);
    $miss = Monitor::factory()->create([
        'name' => 'DNS check',
        'target' => 'https://dns.example',
        'description' => null,
        'tags' => [],
    ]);

    $ids = FuzzySearch::apply(Monitor::query(), 'payments', ['name', 'target', 'description', 'tags'])
        ->pluck('id')
        ->all();

    expect($ids)->toContain($hit->id)
        ->and($ids)->not->toContain($miss->id);

    expect(
        FuzzySearch::apply(Monitor::query(), 'BILLING', ['name', 'target', 'description', 'tags'])->pluck('id')->all()
    )->toContain($hit->id);

    expect(
        FuzzySearch::apply(Monitor::query(), 'prod', ['name', 'target', 'description', 'tags'])->pluck('id')->all()
    )->toContain($hit->id);
});

it('requires every token to match', function () {
    $both = Monitor::factory()->create([
        'name' => 'Payments Gateway',
        'target' => 'https://pay.example',
        'description' => null,
        'tags' => [],
    ]);
    $one = Monitor::factory()->create([
        'name' => 'Payments API',
        'target' => 'https://pay.example',
        'description' => null,
        'tags' => [],
    ]);

    $ids = FuzzySearch::apply(Monitor::query(), 'payments gateway', ['name', 'target', 'description', 'tags'])
        ->pluck('id')
        ->all();

    expect($ids)->toContain($both->id)
        ->and($ids)->not->toContain($one->id);
});

it('matches a single-character typo for longer tokens', function () {
    $hit = Monitor::factory()->create([
        'name' => 'Checkout Gateway',
        'target' => 'https://checkout.example',
        'description' => null,
        'tags' => [],
    ]);

    $ids = FuzzySearch::apply(Monitor::query(), 'gatewax', ['name', 'target', 'description', 'tags'])
        ->pluck('id')
        ->all();

    expect($ids)->toContain($hit->id);
});
