<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentImpact;
use App\Enums\IncidentStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'status_page_id',
    'title',
    'status',
    'impact',
    'started_at',
    'resolved_at',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'impact' => IncidentImpact::class,
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function statusPage(): BelongsTo
    {
        return $this->belongsTo(StatusPage::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class)->orderByDesc('posted_at');
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'incident_monitor');
    }

    public function isResolved(): bool
    {
        return $this->status->isResolved() || $this->resolved_at !== null;
    }

    public function isActive(): bool
    {
        if ($this->isResolved()) {
            return false;
        }

        if ($this->status === IncidentStatus::Scheduled && $this->started_at->isFuture()) {
            return false;
        }

        return true;
    }

    public function isMaintenance(): bool
    {
        return $this->status === IncidentStatus::Scheduled && ! $this->isResolved();
    }

    /**
     * @param  Builder<Incident>  $query
     * @return Builder<Incident>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', IncidentStatus::Resolved)
            ->whereNull('resolved_at');
    }
}
