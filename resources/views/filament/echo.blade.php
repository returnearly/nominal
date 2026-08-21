@if (config('filament.broadcasting.echo'))
    <script data-navigate-once>
        (() => {
            if (! window.Echo || window.NominalMonitorsEcho) {
                return
            }

            window.NominalMonitorsEcho = true

            let timer

            const notify = () => {
                clearTimeout(timer)
                timer = setTimeout(() => window.Livewire?.dispatch('monitors-updated'), 250)
            }

            window.Echo.private('monitors')
                .listen('.CheckCompleted', notify)
                .listen('.MonitorStatusUpdated', notify)
        })()
    </script>
@endif
