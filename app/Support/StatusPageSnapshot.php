<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\LoadRecentCheckResults;
use App\Enums\StatusPageHealth;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Support\Collection;

final readonly class StatusPageSnapshot
{
    /**
     * @param  Collection<int, Monitor>  $monitors
     * @param  Collection<string, Collection<int, Monitor>>  $groups
     * @param  Collection<string, Collection<int, CheckResult>>  $recentChecks
     * @param  Collection<int, Incident>  $activeIncidents
     * @param  Collection<int, Incident>  $scheduledIncidents
     * @param  Collection<int, Incident>  $pastIncidents
     */
    public function __construct(
        public StatusPage $page,
        public StatusPageHealth $health,
        public Collection $monitors,
        public Collection $groups,
        public Collection $recentChecks,
        public Collection $activeIncidents,
        public Collection $scheduledIncidents,
        public Collection $pastIncidents,
        public bool $onCustomDomain,
    ) {}

    public static function for(StatusPage $page, bool $onCustomDomain = false): self
    {
        $page->loadMissing([
            'listings.monitor',
            'incidents.updates',
            'incidents.monitors',
        ]);

        $monitors = $page->listings
            ->map(function ($listing): ?Monitor {
                $monitor = $listing->monitor;

                if ($monitor === null) {
                    return null;
                }

                $monitor->setAttribute('public_display_name', $listing->displayName());

                return $monitor;
            })
            ->filter()
            ->values();

        $groups = $monitors->groupBy(fn (Monitor $monitor): string => filled($monitor->group) ? (string) $monitor->group : 'Services');

        $recentChecks = LoadRecentCheckResults::make()->handle($monitors->pluck('id'));

        $incidents = $page->incidents;
        $active = $incidents->filter(fn (Incident $incident): bool => $incident->isActive())->values();
        $scheduled = $incidents->filter(fn (Incident $incident): bool => $incident->isMaintenance() && ! $incident->isActive())->values();
        $past = $incidents->filter(fn (Incident $incident): bool => $incident->isResolved())->take(10)->values();

        return new self(
            page: $page,
            health: StatusPageHealth::fromMonitorsAndIncidents($monitors, $incidents),
            monitors: $monitors,
            groups: $groups,
            recentChecks: $recentChecks,
            activeIncidents: $active,
            scheduledIncidents: $scheduled,
            pastIncidents: $past,
            onCustomDomain: $onCustomDomain,
        );
    }
}
