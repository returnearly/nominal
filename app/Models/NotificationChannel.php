<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannelType;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'type', 'config'])]
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'config' => AsEncryptedArrayObject::class,
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class)
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
     * @return array<string, mixed>
     */
    public function configArray(): array
    {
        return (array) ($this->config ?? []);
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    public function graphqlConfig(): array
    {
        $pairs = [];

        foreach ($this->configArray() as $key => $value) {
            $pairs[] = [
                'key' => (string) $key,
                'value' => is_scalar($value) ? (string) $value : (string) json_encode($value),
            ];
        }

        return $pairs;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->configArray()['to'] ?? null;
    }

    public function routeNotificationForSlack(): ?string
    {
        return $this->configArray()['webhook_url'] ?? null;
    }
}
