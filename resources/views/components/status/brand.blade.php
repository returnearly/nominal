@props(['page', 'homeUrl'])

<a href="{{ $homeUrl }}" class="nm-status-brand">
    @if (filled($page->logo_url))
        <img src="{{ $page->logo_url }}" alt="{{ $page->name }}" class="nm-status-logo">
    @else
        <span class="nm-status-logo">@include('filament.logo')</span>
    @endif
    <span>
        <span class="nm-status-title">{{ $page->name }}</span>
        @if (filled($page->headline))
            <p class="nm-status-kicker">{{ $page->headline }}</p>
        @endif
    </span>
</a>
