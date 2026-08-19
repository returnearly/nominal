<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'group',
    'type',
    'enabled',
    'interval_seconds',
    'timeout_seconds',
    'ip_family',
    'target',
    'method',
    'request_headers',
    'request_body',
    'follow_redirects',
    'verify_tls',
    'status',
    'last_checked_at',
    'last_status_changed_at',
    'consecutive_successes',
    'consecutive_failures',
    'retention_days',
])]
class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => MonitorType::class,
            'ip_family' => IpFamily::class,
            'method' => HttpMethod::class,
            'status' => MonitorStatus::class,
            'enabled' => 'boolean',
            'follow_redirects' => 'boolean',
            'verify_tls' => 'boolean',
            'request_headers' => AsEncryptedArrayObject::class,
            'last_checked_at' => 'datetime',
            'last_status_changed_at' => 'datetime',
            'interval_seconds' => 'integer',
            'timeout_seconds' => 'integer',
            'consecutive_successes' => 'integer',
            'consecutive_failures' => 'integer',
            'retention_days' => 'integer',
        ];
    }

    public function probes(): BelongsToMany
    {
        return $this->belongsToMany(Probe::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(MonitorCondition::class)->orderBy('sort');
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(CheckResult::class)->orderByDesc('checked_at');
    }

    public function checkAggregates(): HasMany
    {
        return $this->hasMany(CheckAggregate::class);
    }

    public function notificationChannels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class)
            ->using(MonitorNotificationChannel::class)
            ->withPivot([
                'failure_threshold',
                'success_threshold',
                'send_on_resolved',
                'reminder_interval_seconds',
                'triggered',
                'last_notified_at',
            ]);
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    public function graphqlRequestHeaders(): array
    {
        $headers = [];

        foreach ($this->requestHeadersArray() as $key => $value) {
            $headers[] = [
                'key' => (string) $key,
                'value' => (string) $value,
            ];
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    public function requestHeadersArray(): array
    {
        $headers = $this->request_headers;

        if ($headers === null) {
            return [];
        }

        return (array) $headers;
    }

    public function isDue(): bool
    {
        if (! $this->enabled || $this->status === MonitorStatus::Paused) {
            return false;
        }

        if ($this->last_checked_at === null) {
            return true;
        }

        return $this->last_checked_at->addSeconds($this->interval_seconds)->isPast();
    }
}
