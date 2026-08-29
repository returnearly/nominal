<x-status.layout :page="$page" :disable-refresh="true">
    <form class="nm-password" method="post" action="{{ $unlockAction }}">
        @csrf
        <h1>{{ $page->name }}</h1>
        <p>This status page is password protected.</p>
        @if ($error)
            <p class="nm-password-error">{{ $error }}</p>
        @endif
        <input type="password" name="password" autocomplete="current-password" required autofocus placeholder="Password">
        <button type="submit">View status</button>
    </form>
</x-status.layout>
