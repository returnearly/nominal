@php
    $homeUrl = $onCustomDomain ? url('/') : $page->pathUrl();
@endphp

<x-status.layout :page="$page">
    <div class="nm-status-shell">
        <header class="nm-status-header">
            <x-status.brand :page="$page" :home-url="$homeUrl" />
        </header>

        <a class="nm-back" href="{{ $homeUrl }}">← All systems</a>

        <article class="nm-incident">
            <div class="nm-incident-head">
                <h1 class="nm-incident-title">{{ $incident->title }}</h1>
                <x-monitor.status-badge :status="$incident->status->value" />
            </div>
            <p class="nm-incident-meta">
                {{ $incident->impact->getLabel() }} impact
                · Started {{ $incident->started_at->toDayDateTimeString() }}
                @if ($incident->resolved_at)
                    · Resolved {{ $incident->resolved_at->toDayDateTimeString() }}
                @endif
            </p>

            @if ($incident->monitors->isNotEmpty())
                <p class="nm-incident-meta">
                    Affected:
                    {{ $incident->monitors->pluck('name')->join(', ') }}
                </p>
            @endif

            <div class="nm-timeline">
                @forelse ($incident->updates as $update)
                    <section class="nm-update">
                        <strong>{{ $update->status->getLabel() }}</strong>
                        <p class="nm-incident-meta">{{ $update->posted_at->toDayDateTimeString() }}</p>
                        <p class="nm-incident-message">{{ $update->message }}</p>
                    </section>
                @empty
                    <p class="nm-incident-meta">No updates yet.</p>
                @endforelse
            </div>
        </article>
    </div>
</x-status.layout>
