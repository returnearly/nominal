<?php

declare(strict_types=1);

use App\Actions\RollupCheckAggregates;
use App\Enums\AggregateGranularity;
use App\Models\CheckAggregate;
use App\Models\Monitor;
use App\Models\Probe;
use Illuminate\Support\Str;

it('writes check aggregates with uuid v7 primary keys', function () {
    $this->freezeTime();

    $monitor = Monitor::factory()->create();
    $probe = Probe::factory()->create();
    $hour = now()->subHour()->startOfHour();

    $monitor->checkResults()->create([
        'probe_id' => $probe->id,
        'checked_at' => $hour->copy()->addMinutes(10),
        'success' => true,
        'latency_ms' => 20,
    ]);

    RollupCheckAggregates::make()->handle($hour);

    $aggregate = CheckAggregate::query()
        ->where('granularity', AggregateGranularity::Hour)
        ->whereNull('probe_id')
        ->first();

    expect($aggregate)->not->toBeNull()
        ->and(Str::isUuid($aggregate->id, 7))->toBeTrue()
        ->and($aggregate->granularity)->toBe(AggregateGranularity::Hour)
        ->and($aggregate->up_count)->toBe(1);

    $daily = CheckAggregate::query()
        ->where('granularity', AggregateGranularity::Day)
        ->whereNull('probe_id')
        ->first();

    expect($daily)->not->toBeNull()
        ->and($daily->period_start->equalTo($hour->copy()->startOfDay()))->toBeTrue()
        ->and($daily->up_count)->toBe(1)
        ->and($daily->avg_latency_ms)->toBe(20);
});
