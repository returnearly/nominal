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

        .fi-wi-stats-overview-stat.nm-stat[data-status="maintenance"] {
            --nm-stat: var(--warning-500);
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

        .nm-skeleton {
            position: relative;
            overflow: hidden;
            background: color-mix(in oklab, var(--nm-sage, #e0eadd) 72%, #fff);
        }

        .nm-skeleton::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, color-mix(in oklab, #fff 55%, transparent), transparent);
            animation: nm-skeleton 1.15s ease-in-out infinite;
        }

        .dark .nm-skeleton {
            background: color-mix(in oklab, #5adeb7 10%, #0c1613);
        }

        .dark .nm-skeleton::after {
            background: linear-gradient(90deg, transparent, color-mix(in oklab, #5adeb7 12%, transparent), transparent);
        }

        @keyframes nm-skeleton {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .nm-stats-placeholder {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        @media (min-width: 768px) {
            .nm-stats-placeholder {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .nm-stats-placeholder-stat {
            min-height: 4.4rem;
            border-radius: 0.9rem;
        }

        .nm-history-placeholder-label,
        .nm-history-placeholder-heading {
            display: block;
            width: 6.5rem;
            height: 0.7rem;
            border-radius: 999px;
        }

        .nm-history-placeholder-heading {
            width: 8.5rem;
            margin-bottom: 0.85rem;
        }

        .nm-history-placeholder-value {
            display: block;
            width: 3.25rem;
            height: 1.35rem;
            border-radius: 0.4rem;
        }

        .nm-history-placeholder-chart {
            height: 2.25rem;
            border-radius: 0.45rem;
        }

        .nm-history-placeholder-chart-tall {
            height: 4.5rem;
        }
    </style>
@endonce
