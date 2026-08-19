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
            background-color: color-mix(in oklab, var(--nm-stat) 16%, var(--color-white));
        }

        .fi-wi-stats-overview-stat.nm-stat .fi-wi-stats-overview-stat-label,
        .fi-wi-stats-overview-stat.nm-stat .fi-wi-stats-overview-stat-value {
            color: var(--nm-stat);
        }

        .fi-wi-stats-overview-stat.nm-stat:where(.dark, .dark *) {
            background-color: color-mix(in oklab, var(--nm-stat) 22%, var(--gray-900));
        }
    </style>
@endonce
