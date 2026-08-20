<?php

declare(strict_types=1);

use App\Enums\IncidentImpact;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Enums\StatusPageHealth;
use App\Models\Incident;
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
