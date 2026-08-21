<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Livewire\Attributes\On;

trait RefreshesOnMonitorBroadcasts
{
    #[On('monitors-updated')]
    public function refreshOnMonitorBroadcast(): void
    {
        $this->onMonitorBroadcast();
    }

    protected function onMonitorBroadcast(): void
    {
        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }
}
