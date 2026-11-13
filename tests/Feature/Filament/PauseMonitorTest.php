<?php

declare(strict_types=1);

use App\Enums\MonitorStatus;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Models\Monitor;
use App\Models\User;
use Livewire\Livewire;

it('pauses and resumes a monitor from the view page', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionVisible('pause')
        ->assertActionHidden('resume')
        ->callAction('pause')
        ->assertNotified('Monitor paused')
        ->assertActionHidden('pause')
        ->assertActionVisible('resume')
        ->callAction('resume')
        ->assertNotified('Monitor resumed')
        ->assertActionVisible('pause')
        ->assertActionHidden('resume');

    expect($monitor->fresh()->status)->toBe(MonitorStatus::Pending);
});

it('hides check now while a monitor is paused', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['status' => MonitorStatus::Paused]);

    Livewire::actingAs($user)
        ->test(ViewMonitor::class, ['record' => $monitor->getRouteKey()])
        ->assertActionHidden('checkNow')
        ->assertActionHidden('pause')
        ->assertActionVisible('resume');
});
