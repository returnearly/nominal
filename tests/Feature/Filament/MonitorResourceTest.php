<?php

declare(strict_types=1);

use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Jobs\RunCheckJob;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('shows monitors in the admin panel', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create();
    $monitor = Monitor::factory()->create(['name' => 'Payments API']);
    $monitor->probes()->attach($probe);

    $this->actingAs($user)
        ->get('/admin/monitors')
        ->assertOk()
        ->assertSee('Payments API');
});

it('queues a check immediately from the monitor view page', function () {
    Queue::fake();

    $user = User::factory()->create();
    $probe = Probe::factory()->create(['queue' => 'checks.local']);
    $monitor = Monitor::factory()->create();
    $monitor->probes()->attach($probe);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionExists('checkNow')
        ->callAction('checkNow');

    Queue::assertPushedOn('checks.local', function (RunCheckJob $job) use ($monitor, $probe): bool {
        return $job->monitorId === $monitor->id && $job->probeId === $probe->id;
    });
});
