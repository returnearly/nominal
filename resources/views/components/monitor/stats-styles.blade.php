@once
    <style>
        .fi-wi-stats-overview-stat.nm-stat[data-status="up"] {
            --nm-stat: var(--success-500);
        }

        .fi-wi-stats-overview-stat.nm-stat[data-status="down"] {
            --nm-stat: var(--danger-500);
        }

        .fi-wi-stats-overview-stat.nm-stat[data-status="pending"] {
            --nm-stat: var(--gray-500);
        }

        .fi-wi-stats-overview-stat.nm-stat[data-status="paused"] {
            --nm-stat: var(--purple-500);
        }

        .fi-wi-stats-overview-stat.nm-stat {
            padding: 0.7rem 0.9rem;
            background-color: color-mix(in oklab, var(--nm-stat) 16%, #fdfff8);
            border: 1px solid color-mix(in oklab, var(--nm-stat) 28%, #e0eadd);
        }

        .fi-wi-stats-overview-stat.nm-stat .fi-wi-stats-overview-stat-content {
            gap: 0.15rem;
        }

        .fi-wi-stats-overview-stat.nm-stat .fi-wi-stats-overview-stat-label,
        .fi-wi-stats-overview-stat.nm-stat .fi-wi-stats-overview-stat-value {
            color: var(--nm-stat);
        }

        .fi-wi-stats-overview-stat.nm-stat .fi-wi-stats-overview-stat-value {
            font-size: 1.35rem;
            line-height: 1.2;
        }

        .fi-wi-stats-overview-stat.nm-stat:where(.dark, .dark *) {
            background-color: color-mix(in oklab, var(--nm-stat) 18%, #0c1613);
            border-color: color-mix(in oklab, var(--nm-stat) 28%, #12201c);
        }

        .fi-ta-content-grid .fi-ta-record,
        .fi-ta-content-grid .fi-ta-record-content-ctn,
        .fi-ta-content-grid .fi-ta-record-content {
            overflow: visible;
        }

        .fi-ta-content-grid .fi-ta-record {
            align-items: stretch;
            padding: 1rem 1.05rem 0.95rem;
        }

        .fi-ta-content-grid .fi-ta-record-content-ctn {
            padding-top: 0;
            padding-bottom: 0;
            gap: 0;
        }

        .fi-ta-content-grid .fi-ta-col,
        .fi-ta-content-grid .nm-card {
            width: 100%;
            padding: 0;
        }
    </style>
@endonce
