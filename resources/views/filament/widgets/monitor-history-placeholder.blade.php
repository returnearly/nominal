<x-monitor.history-styles />

<div class="nm-detail">
    <div class="nm-stat-cards">
        @foreach (range(1, 4) as $i)
            <div class="nm-panel nm-stat-card">
                <span class="nm-skeleton nm-history-placeholder-label"></span>
                <span class="nm-skeleton nm-history-placeholder-value"></span>
            </div>
        @endforeach
    </div>
    <section class="nm-panel">
        <span class="nm-skeleton nm-history-placeholder-heading"></span>
        <div class="nm-skeleton nm-history-placeholder-chart"></div>
    </section>
    <section class="nm-panel">
        <span class="nm-skeleton nm-history-placeholder-heading"></span>
        <div class="nm-skeleton nm-history-placeholder-chart nm-history-placeholder-chart-tall"></div>
    </section>
</div>
