<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MonitorNotificationChannel extends Pivot
{
    protected $table = 'monitor_notification_channel';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'failure_threshold' => 'integer',
            'success_threshold' => 'integer',
            'send_on_resolved' => 'boolean',
            'reminder_interval_seconds' => 'integer',
            'triggered' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function notificationChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }
}
