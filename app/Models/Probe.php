<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProbeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'queue', 'enabled', 'is_default'])]
class Probe extends Model
{
    /** @use HasFactory<ProbeFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Probe>  $query
     * @return Builder<Probe>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true)->where('enabled', true);
    }

    /**
     * @return list<string>
     */
    public static function defaultIds(): array
    {
        return static::query()->default()->pluck('id')->all();
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }

    public function queueName(): string
    {
        return $this->queue;
    }
}
