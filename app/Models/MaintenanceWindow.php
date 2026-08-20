<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MaintenanceWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Fillable([
    'title',
    'message',
    'starts_at',
    'ends_at',
    'applies_to_all',
    'cancelled_at',
])]
class MaintenanceWindow extends Model
{
    /** @use HasFactory<MaintenanceWindowFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'applies_to_all' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Monitor, $this>
     */
    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereNull('cancelled_at')
            ->where('starts_at', '<=', $now)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCovering(Builder $query, Monitor $monitor): Builder
    {
        return $query->active()->where(function (Builder $query) use ($monitor): void {
            $query->where('applies_to_all', true)
                ->orWhereHas('monitors', fn (Builder $monitors) => $monitors->whereKey($monitor->id));
        });
    }

    public function isActive(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->cancelled_at !== null) {
            return false;
        }

        if ($this->starts_at->gt($at)) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->gt($at);
    }

    public function isScheduled(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->cancelled_at === null && $this->starts_at->gt($at);
    }

    public function covers(Monitor $monitor): bool
    {
        if ($this->applies_to_all) {
            return true;
        }

        if ($this->relationLoaded('monitors')) {
            return $this->monitors->contains($monitor);
        }

        return $this->monitors()->whereKey($monitor->id)->exists();
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     */
    public static function primeMonitors(Collection $monitors): void
    {
        if ($monitors->isEmpty()) {
            return;
        }

        $windows = static::query()
            ->active()
            ->with('monitors:id')
            ->orderBy('starts_at')
            ->get();

        foreach ($monitors as $monitor) {
            $monitor->setRelation(
                'activeMaintenanceWindow',
                $windows->first(fn (self $window): bool => $window->covers($monitor)),
            );
        }
    }

    public function phase(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($this->isActive()) {
            return 'active';
        }

        if ($this->isScheduled()) {
            return 'scheduled';
        }

        return 'ended';
    }
}
