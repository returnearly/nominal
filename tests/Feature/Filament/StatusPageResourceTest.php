<?php

declare(strict_types=1);

use App\Enums\StatusPageTheme;
use App\Filament\Resources\StatusPages\Pages\CreateStatusPage;
use App\Filament\Resources\StatusPages\Pages\EditStatusPage;
use App\Filament\Resources\StatusPages\Pages\ListStatusPages;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Livewire\Livewire;

it('shows status pages in the admin panel', function () {
    $user = User::factory()->create();
    StatusPage::factory()->create(['name' => 'Acme Status']);

    $this->actingAs($user)
        ->get('/admin/status-pages')
        ->assertOk();

    Livewire::actingAs($user)
        ->test(ListStatusPages::class)
        ->loadTable()
        ->assertSee('Acme Status');
});

it('creates a status page with listed monitors', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['name' => 'Payments API']);

    Livewire::actingAs($user)
        ->test(CreateStatusPage::class)
        ->fillForm([
            'name' => 'Acme Status',
            'slug' => 'acme',
            'headline' => 'Customer services',
            'theme' => StatusPageTheme::Dark,
            'published' => true,
            'show_targets' => false,
            'refresh_seconds' => 30,
            'listings' => [
                [
                    'monitor_id' => $monitor->id,
                    'public_name' => 'API',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = StatusPage::query()->where('slug', 'acme')->first();

    expect($page)->not->toBeNull()
        ->and($page?->published)->toBeTrue()
        ->and($page?->listings)->toHaveCount(1)
        ->and($page?->listings->first()?->public_name)->toBe('API')
        ->and($page?->listings->first()?->monitor_id)->toBe($monitor->id);
});

it('can open a published status page from the edit screen', function () {
    $user = User::factory()->create();
    $page = StatusPage::factory()->create(['slug' => 'acme', 'published' => true]);

    Livewire::actingAs($user)
        ->test(EditStatusPage::class, ['record' => $page->id])
        ->assertActionVisible('open');
});

it('hides the open action on unpublished pages', function () {
    $user = User::factory()->create();
    $page = StatusPage::factory()->unpublished()->create();

    Livewire::actingAs($user)
        ->test(EditStatusPage::class, ['record' => $page->id])
        ->assertActionHidden('open');
});

it('lists status pages in the Filament table', function () {
    $user = User::factory()->create();
    $page = StatusPage::factory()->create(['name' => 'Acme Status']);

    Livewire::actingAs($user)
        ->test(ListStatusPages::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$page]);
});
