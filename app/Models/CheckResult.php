<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CheckResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'monitor_id',
    'probe_id',
    'checked_at',
    'success',
    'latency_ms',
    'http_status',
    'resolved_ip',
    'certificate_expires_at',
    'domain_expires_at',
    'message',
    'condition_results',
])]
class CheckResult extends Model
{
    /** @use HasFactory<CheckResultFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'success' => 'boolean',
            'latency_ms' => 'integer',
            'http_status' => 'integer',
            'certificate_expires_at' => 'datetime',
            'domain_expires_at' => 'datetime',
            'condition_results' => 'array',
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

    public function graphqlConditionResults(): string
    {
        return json_encode($this->condition_results ?? []) ?: '[]';
    }
}
