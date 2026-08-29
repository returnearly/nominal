<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentStatus;
use Database\Factories\IncidentUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incident_id',
    'status',
    'message',
    'posted_at',
])]
class IncidentUpdate extends Model
{
    /** @use HasFactory<IncidentUpdateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    protected static function booted(): void
    {
        static::creating(function (IncidentUpdate $update): void {
            $update->posted_at ??= now();
        });
    }
}
