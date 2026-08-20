<?php

declare(strict_types=1);

use App\Enums\IncidentImpact;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\IncidentUpdate;
use App\Models\Monitor;
use App\Models\StatusPage;

it('redirects the app host home page to the admin panel', function () {
    $this->get('/')->assertRedirect('/admin');
});

it('renders a published status page without monitor targets', function () {
    $page = StatusPage::factory()->create([
        'name' => 'Acme Status',
        'headline' => 'Customer services',
        'slug' => 'acme',
        'show_targets' => false,
    ]);
    $monitor = Monitor::factory()->create([
        'name' => 'Internal API',
        'target' => 'https://secret.internal/health',
        'status' => MonitorStatus::Up,
        'group' => 'core',
    ]);
    $page->listings()->create([
        'monitor_id' => $monitor->id,
        'public_name' => 'API',
        'sort' => 0,
    ]);

    $this->get('/status/acme')
        ->assertOk()
        ->assertSee('Acme Status')
        ->assertSee('Customer services')
        ->assertSee('All systems operational')
        ->assertSee('API')
        ->assertDontSee('Internal API')
        ->assertDontSee('https://secret.internal/health');
});

it('hides unpublished status pages', function () {
    $page = StatusPage::factory()->unpublished()->create(['slug' => 'draft']);

    $this->get('/status/draft')->assertNotFound();
});

it('shows active incidents and their public timeline', function () {
    $page = StatusPage::factory()->create(['slug' => 'acme', 'name' => 'Acme Status']);
    $incident = Incident::factory()->create([
        'status_page_id' => $page->id,
        'title' => 'API latency',
        'status' => IncidentStatus::Investigating,
        'impact' => IncidentImpact::Minor,
    ]);
    IncidentUpdate::factory()->create([
        'incident_id' => $incident->id,
        'status' => IncidentStatus::Investigating,
        'message' => 'We are looking into elevated latency.',
    ]);

    $this->get('/status/acme')
        ->assertOk()
        ->assertSee('Active incidents')
        ->assertSee('API latency')
        ->assertSee('We are looking into elevated latency.');

    $this->get('/status/acme/incidents/'.$incident->id)
        ->assertOk()
        ->assertSee('API latency')
        ->assertSee('We are looking into elevated latency.')
        ->assertSee('Investigating');
});

it('lists upcoming maintenance separately from active incidents', function () {
    $page = StatusPage::factory()->create(['slug' => 'acme']);
    Incident::factory()->scheduled()->create([
        'status_page_id' => $page->id,
        'title' => 'Database upgrade',
    ]);

    $this->get('/status/acme')
        ->assertOk()
        ->assertSee('Scheduled maintenance')
        ->assertSee('Database upgrade')
        ->assertDontSee('Active incidents');
});

it('requires a password before showing a protected page', function () {
    $page = StatusPage::factory()->passwordProtected('secret')->create([
        'slug' => 'private',
        'name' => 'Private Status',
    ]);

    $this->get('/status/private')
        ->assertOk()
        ->assertSee('This status page is password protected.')
        ->assertDontSee('All systems operational');

    $this->from('/status/private')
        ->post('/status/private/unlock', [
            'password' => 'nope',
            '_token' => csrf_token(),
        ])
        ->assertUnprocessable()
        ->assertSee('That password is incorrect.');

    $this->from('/status/private')
        ->post('/status/private/unlock', [
            'password' => 'secret',
            '_token' => csrf_token(),
        ])
        ->assertRedirect('/status/private');

    $this->get('/status/private')
        ->assertOk()
        ->assertSee('Private Status')
        ->assertSee('All systems operational');
});

it('serves a published page at / on its custom domain', function () {
    config(['app.url' => 'http://nominal.test']);

    $page = StatusPage::factory()->onDomain('status.acme.test')->create([
        'name' => 'Acme Status',
        'slug' => 'acme',
    ]);
    $incident = Incident::factory()->create([
        'status_page_id' => $page->id,
        'title' => 'Edge outage',
    ]);

    $this->get('http://status.acme.test/')
        ->assertOk()
        ->assertSee('Acme Status')
        ->assertSee('Edge outage');

    $this->get('http://status.acme.test/incidents/'.$incident->id)
        ->assertOk()
        ->assertSee('Edge outage');

    $this->get('http://nominal.test/')->assertRedirect('/admin');
});

it('does not serve unpublished custom domains', function () {
    config(['app.url' => 'http://nominal.test']);

    StatusPage::factory()->unpublished()->onDomain('status.acme.test')->create();

    $this->get('http://status.acme.test/')->assertNotFound();
});
