<x-monitor.card :monitor="$record" :checks="$record->recentChecks ?? collect()" />
