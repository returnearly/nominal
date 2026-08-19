<div class="nm-history">
    <div>
        <p class="nm-history-label">Last 20 checks</p>
        <x-monitor.heartbeat :checks="$checks" :show-latency="true" />
    </div>
    <div>
        <p class="nm-history-label">Last 7 days by hour</p>
        <x-monitor.heatmap :cells="$cells" />
    </div>
</div>
