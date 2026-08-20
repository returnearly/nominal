@php
    $page = $snapshot->page;
    $homeUrl = $snapshot->onCustomDomain ? url('/') : $page->pathUrl();
@endphp

<x-status.layout :page="$page">
    <div class="nm-status-shell">
        <header class="nm-status-header">
            <x-status.brand :page="$page" :home-url="$homeUrl" />
        </header>

        <div class="nm-banner" data-health="{{ $snapshot->health->value }}">
            <span>{{ $snapshot->health->getLabel() }}</span>
            <span class="nm-banner-time">{{ now()->toDayDateTimeString() }}</span>
        </div>

        @if (filled($page->description))
            <p class="nm-status-copy">{{ $page->description }}</p>
        @endif

        @if ($snapshot->activeIncidents->isNotEmpty())
            <section class="nm-group">
                <h2 class="nm-group-title">Active incidents</h2>
                @foreach ($snapshot->activeIncidents as $incident)
                    <a class="nm-incident" href="{{ $page->incidentPath($incident, $snapshot->onCustomDomain) }}">
                        <div class="nm-incident-head">
                            <h3 class="nm-incident-title">{{ $incident->title }}</h3>
                            <x-monitor.status-badge :status="$incident->status->value" />
                        </div>
                        <p class="nm-incident-meta">
                            {{ $incident->impact->getLabel() }} impact
                            · {{ $incident->started_at->diffForHumans() }}
                        </p>
                        @if ($incident->updates->isNotEmpty())
                            <p class="nm-incident-message">{{ $incident->updates->first()->message }}</p>
                        @endif
                    </a>
                @endforeach
            </section>
        @endif

        @if ($snapshot->activeWindows->isNotEmpty())
            <section class="nm-group">
                <h2 class="nm-group-title">Maintenance</h2>
                @foreach ($snapshot->activeWindows as $window)
                    <div class="nm-incident">
                        <div class="nm-incident-head">
                            <h3 class="nm-incident-title">{{ $window->title }}</h3>
                            <x-monitor.status-badge status="maintenance" />
                        </div>
                        <p class="nm-incident-meta">
                            @if ($window->ends_at)
                                Until {{ $window->ends_at->toDayDateTimeString() }}
                            @else
                                In progress
                            @endif
                        </p>
                        @if (filled($window->message))
                            <p class="nm-incident-message">{{ $window->message }}</p>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        @if ($snapshot->scheduledIncidents->isNotEmpty() || $snapshot->scheduledWindows->isNotEmpty())
            <section class="nm-group">
                <h2 class="nm-group-title">Scheduled maintenance</h2>
                @foreach ($snapshot->scheduledIncidents as $incident)
                    <a class="nm-incident" href="{{ $page->incidentPath($incident, $snapshot->onCustomDomain) }}">
                        <div class="nm-incident-head">
                            <h3 class="nm-incident-title">{{ $incident->title }}</h3>
                            <x-monitor.status-badge :status="$incident->status->value" />
                        </div>
                        <p class="nm-incident-meta">Starts {{ $incident->started_at->toDayDateTimeString() }}</p>
                    </a>
                @endforeach
                @foreach ($snapshot->scheduledWindows as $window)
                    <div class="nm-incident">
                        <div class="nm-incident-head">
                            <h3 class="nm-incident-title">{{ $window->title }}</h3>
                            <x-monitor.status-badge status="scheduled" />
                        </div>
                        <p class="nm-incident-meta">Starts {{ $window->starts_at->toDayDateTimeString() }}</p>
                        @if (filled($window->message))
                            <p class="nm-incident-message">{{ $window->message }}</p>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        @forelse ($snapshot->groups as $group => $monitors)
            <section class="nm-group">
                <h2 class="nm-group-title">{{ $group }}</h2>
                <div class="nm-status-grid">
                    @foreach ($monitors as $monitor)
                        <div class="nm-status-tile">
                            <x-monitor.card
                                :monitor="$monitor"
                                :checks="$snapshot->recentChecks->get($monitor->id, collect())"
                                :show-target="$page->show_targets"
                                :name="$monitor->public_display_name"
                            />
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="nm-status-copy">No monitors are listed on this status page yet.</p>
        @endforelse

        @if ($snapshot->pastIncidents->isNotEmpty())
            <section class="nm-group">
                <h2 class="nm-group-title">Past incidents</h2>
                @foreach ($snapshot->pastIncidents as $incident)
                    <a class="nm-incident" href="{{ $page->incidentPath($incident, $snapshot->onCustomDomain) }}">
                        <div class="nm-incident-head">
                            <h3 class="nm-incident-title">{{ $incident->title }}</h3>
                            <x-monitor.status-badge :status="$incident->status->value" />
                        </div>
                        <p class="nm-incident-meta">
                            Resolved {{ $incident->resolved_at?->diffForHumans() ?? $incident->updated_at->diffForHumans() }}
                        </p>
                    </a>
                @endforeach
            </section>
        @endif

        @if (filled($page->footer_text))
            <footer class="nm-status-footer">{{ $page->footer_text }}</footer>
        @endif
    </div>
</x-status.layout>
