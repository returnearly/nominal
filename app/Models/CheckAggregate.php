<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AggregateGranularity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'monitor_id',
    'probe_id',
    'period_start',
    'granularity',
    'up_count',
    'down_count',
    'avg_latency_ms',
])]
class CheckAggregate extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'granularity' => AggregateGranularity::class,
            'up_count' => 'integer',
            'down_count' => 'integer',
            'avg_latency_ms' => 'integer',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function probe(): BelongsTo
    {
        return $this->belongsTo(Probe::class);
    }
}
