<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DnsQueryType;
use App\Enums\HeartbeatSignal;
use App\Enums\HttpMethod;
use App\Enums\IpFamily;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use Database\Factories\MonitorFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
    'dns_query_name',
    'dns_query_type',
    'heartbeat_token',
    'follow_redirects',
    'verify_tls',
    'status',
    'last_checked_at',
    'next_check_at',
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
            'dns_query_type' => DnsQueryType::class,
            'status' => MonitorStatus::class,
            'enabled' => 'boolean',
            'follow_redirects' => 'boolean',
            'verify_tls' => 'boolean',
            'request_headers' => AsEncryptedArrayObject::class,
            'last_checked_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'heartbeat_started_at' => 'datetime',
            'next_check_at' => 'datetime',
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

    public function maintenanceWindows(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceWindow::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnderMaintenance(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereHas('maintenanceWindows', fn (Builder $windows) => $windows->active())
                ->orWhereExists(function ($sub): void {
                    $sub->from('maintenance_windows')
                        ->where('applies_to_all', true)
                        ->whereNull('cancelled_at')
                        ->where('starts_at', '<=', now())
                        ->where(function ($inner): void {
                            $inner->whereNull('ends_at')->orWhere('ends_at', '>', now());
                        });
                });
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotUnderMaintenance(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $query) => $query->underMaintenance());
    }

    public function activeMaintenanceWindow(): ?MaintenanceWindow
    {
        if ($this->relationLoaded('activeMaintenanceWindow')) {
            return $this->getRelation('activeMaintenanceWindow');
        }

        $window = MaintenanceWindow::query()->covering($this)->first();
        $this->setRelation('activeMaintenanceWindow', $window);

        return $window;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->activeMaintenanceWindow() !== null;
    }

    public function hasDirectMaintenance(): bool
    {
        return MaintenanceWindow::query()
            ->active()
            ->where('applies_to_all', false)
            ->whereHas('monitors', fn (Builder $query) => $query->whereKey($this->id))
            ->exists();
    }

    public function effectiveStatus(): MonitorStatus
    {
        if ($this->isUnderMaintenance()) {
            return MonitorStatus::Maintenance;
        }

        return $this->status;
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
        if (! $this->enabled || $this->status === MonitorStatus::Paused || $this->next_check_at === null) {
            return false;
        }

        return $this->next_check_at->lessThanOrEqualTo(now());
    }

    public function scheduleNextCheck(?DateTimeInterface $from = null): static
    {
        $this->next_check_at = Carbon::parse($from ?? now())
            ->copy()
            ->addSeconds($this->interval_seconds);

        return $this;
    }

    public function heartbeatUrl(HeartbeatSignal|string|null $signal = null): ?string
    {
        if ($this->type !== MonitorType::Heartbeat || blank($this->heartbeat_token)) {
            return null;
        }

        $url = url('/api/heartbeat/'.$this->heartbeat_token);
        $suffix = $signal instanceof HeartbeatSignal ? $signal->value : trim((string) $signal);

        return $suffix === '' ? $url : $url.'/'.$suffix;
    }

    public function heartbeatStartUrl(): ?string
    {
        return $this->heartbeatUrl(HeartbeatSignal::Start);
    }

    public function heartbeatFinishUrl(): ?string
    {
        return $this->heartbeatUrl(HeartbeatSignal::Finish);
    }

    public function heartbeatErrorUrl(): ?string
    {
        return $this->heartbeatUrl(HeartbeatSignal::Error);
    }

    public function heartbeatIsRunning(): bool
    {
        return $this->heartbeat_started_at !== null
            && $this->heartbeat_started_at->gt(now()->subSeconds($this->interval_seconds));
    }

    public function heartbeatIsHung(): bool
    {
        return $this->heartbeat_started_at !== null
            && $this->heartbeat_started_at->lte(now()->subSeconds($this->interval_seconds));
    }

    protected static function booted(): void
    {
        static::creating(function (Monitor $monitor): void {
            if ($monitor->type === MonitorType::Heartbeat) {
                $monitor->heartbeat_token ??= Str::random(48);
                $monitor->target = filled($monitor->target) ? $monitor->target : '/api/heartbeat/'.$monitor->heartbeat_token;
                $monitor->next_check_at ??= now()->addSeconds(max(10, (int) $monitor->interval_seconds));

                return;
            }

            $monitor->next_check_at ??= now();
        });
    }
}
