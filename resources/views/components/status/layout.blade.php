@props([
    'page',
    'disableRefresh' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $page->theme->value === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $page->name }}</title>
        <meta name="description" content="{{ $page->headline ?: $page->description ?: $page->name }}">
        <link rel="icon" href="{{ $page->favicon_url ?: asset('favicon.svg') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
        @if ($page->refresh_seconds > 0 && ! $disableRefresh)
            <meta http-equiv="refresh" content="{{ (int) $page->refresh_seconds }}">
        @endif
        @include('status.styles')
        <x-monitor.history-styles />
        @if (filled($page->custom_css))
            <style>{!! $page->custom_css !!}</style>
        @endif
    </head>
    <body {{ $attributes->class(['nm-status', 'dark' => $page->theme->value === 'dark']) }}>
        {{ $slot }}
    </body>
</html>
