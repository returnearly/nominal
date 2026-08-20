<?php

declare(strict_types=1);

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;

final class MonitorCardColumn extends Column
{
    protected string $view = 'filament.tables.columns.monitor-card';

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Monitor')
            ->searchable(query: function (Builder $query, string $search): Builder {
                $like = '%'.$search.'%';

                return $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('target', 'like', $like)
                        ->orWhere('group', 'like', $like);
                });
            })
            ->sortable();
    }
}
