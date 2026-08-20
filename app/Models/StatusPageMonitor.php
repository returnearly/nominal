<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['status_page_id', 'monitor_id', 'public_name', 'sort'])]
class StatusPageMonitor extends Model
{
    use HasUuids;

    protected $table = 'status_page_monitor';

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    public function statusPage(): BelongsTo
    {
        return $this->belongsTo(StatusPage::class);
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function displayName(): string
    {
        if (filled($this->public_name)) {
            return (string) $this->public_name;
        }

        return (string) ($this->monitor?->name ?? '');
    }
}
