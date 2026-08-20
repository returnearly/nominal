<?php

declare(strict_types=1);

use App\Enums\IncidentImpact;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Enums\StatusPageHealth;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;

it('is operational when every monitor is up', function () {
    $monitors = collect([
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Up]),
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Up]),
    ]);

    expect(StatusPageHealth::fromMonitorsAndIncidents($monitors, collect()))
        ->toBe(StatusPageHealth::Operational);
});

it('is a partial outage when some monitors are down', function () {
    $monitors = collect([
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Up]),
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Down]),
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Up]),
    ]);

    expect(StatusPageHealth::fromMonitorsAndIncidents($monitors, collect()))
        ->toBe(StatusPageHealth::PartialOutage);
});

it('is a major outage when most monitors are down', function () {
    $monitors = collect([
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Down]),
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Down]),
        new Monitor(['enabled' => true, 'status' => MonitorStatus::Up]),
    ]);

    expect(StatusPageHealth::fromMonitorsAndIncidents($monitors, collect()))
        ->toBe(StatusPageHealth::MajorOutage);
});

it('degrades for an active minor incident', function () {
    $incident = new Incident([
        'status' => IncidentStatus::Investigating,
        'impact' => IncidentImpact::Minor,
        'started_at' => now()->subHour(),
        'resolved_at' => null,
    ]);

    expect(StatusPageHealth::fromMonitorsAndIncidents(collect(), collect([$incident])))
        ->toBe(StatusPageHealth::Degraded);
});

it('is maintenance for upcoming scheduled work', function () {
    $incident = new Incident([
        'status' => IncidentStatus::Scheduled,
        'impact' => IncidentImpact::None,
        'started_at' => now()->addDay(),
        'resolved_at' => null,
    ]);

    expect(StatusPageHealth::fromMonitorsAndIncidents(collect(), collect([$incident])))
        ->toBe(StatusPageHealth::Maintenance);
});

it('is maintenance when listed monitors are under a window', function () {
    $monitor = new Monitor(['enabled' => true, 'status' => MonitorStatus::Down]);
    $monitor->setRelation('activeMaintenanceWindow', new MaintenanceWindow(['title' => 'Upgrade']));

    expect(StatusPageHealth::fromMonitorsAndIncidents(collect([$monitor]), collect()))
        ->toBe(StatusPageHealth::Maintenance);
});

it('keeps a real outage ahead of maintenance', function () {
    $down = new Monitor(['enabled' => true, 'status' => MonitorStatus::Down]);
    $down->setRelation('activeMaintenanceWindow', null);
    $maintained = new Monitor(['enabled' => true, 'status' => MonitorStatus::Up]);
    $maintained->setRelation('activeMaintenanceWindow', new MaintenanceWindow(['title' => 'Upgrade']));
    $up = new Monitor(['enabled' => true, 'status' => MonitorStatus::Up]);
    $up->setRelation('activeMaintenanceWindow', null);

    expect(StatusPageHealth::fromMonitorsAndIncidents(collect([$down, $maintained, $up]), collect()))
        ->toBe(StatusPageHealth::PartialOutage);
});
