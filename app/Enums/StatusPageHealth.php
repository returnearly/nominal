<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Incident;
use App\Models\Monitor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Collection;

enum StatusPageHealth: string implements HasColor, HasLabel
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case PartialOutage = 'partial_outage';
    case MajorOutage = 'major_outage';
    case Maintenance = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Operational => 'All systems operational',
            self::Degraded => 'Degraded performance',
            self::PartialOutage => 'Partial outage',
            self::MajorOutage => 'Major outage',
            self::Maintenance => 'Under maintenance',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Operational => 'success',
            self::Degraded => 'warning',
            self::PartialOutage => 'warning',
            self::MajorOutage => 'danger',
            self::Maintenance => 'purple',
        };
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @param  Collection<int, Incident>  $incidents
     */
    public static function fromMonitorsAndIncidents(Collection $monitors, Collection $incidents): self
    {
        $enabled = $monitors->filter(fn ($monitor): bool => (bool) $monitor->enabled);
        $down = $enabled->filter(fn ($monitor): bool => $monitor->status === MonitorStatus::Down);
        $active = $incidents->filter(fn ($incident): bool => $incident->isActive());
        $maintenance = $incidents->filter(fn ($incident): bool => $incident->isMaintenance());

        if ($enabled->isNotEmpty() && $down->count() === $enabled->count()) {
            return self::MajorOutage;
        }

        if ($down->isNotEmpty()) {
            return $down->count() / max(1, $enabled->count()) >= 0.5
                ? self::MajorOutage
                : self::PartialOutage;
        }

        $worstImpact = $active
            ->map(fn ($incident): IncidentImpact => $incident->impact)
            ->sortBy(fn (IncidentImpact $impact): int => match ($impact) {
                IncidentImpact::Critical => 0,
                IncidentImpact::Major => 1,
                IncidentImpact::Minor => 2,
                IncidentImpact::None => 3,
            })
            ->first();

        if ($worstImpact === IncidentImpact::Critical || $worstImpact === IncidentImpact::Major) {
            return self::MajorOutage;
        }

        if ($worstImpact === IncidentImpact::Minor) {
            return self::Degraded;
        }

        if ($maintenance->isNotEmpty()) {
            return self::Maintenance;
        }

        if ($active->isNotEmpty()) {
            return self::Degraded;
        }

        return self::Operational;
    }
}
