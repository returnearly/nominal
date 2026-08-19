@once
    <style>
        .nm-heartbeat,
        .nm-latency {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            width: 11rem;
            min-width: 11rem;
        }

        .nm-heartbeat {
            height: 1.15rem;
        }

        .nm-latency {
            height: 2.75rem;
        }

        .nm-heartbeat span,
        .nm-latency span {
            flex: 1 1 0;
            min-width: 3px;
            border-radius: 2px;
        }

        .nm-beat-up {
            background: #10b981;
        }

        .nm-beat-down {
            background: #f43f5e;
        }

        .nm-beat-empty {
            background: #d4d4d8;
        }

        .dark .nm-beat-empty {
            background: #3f3f46;
        }

        .nm-history {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .nm-history-label {
            margin: 0 0 0.4rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: color-mix(in oklab, var(--gray-950, #18181b) 70%, transparent);
        }

        .dark .nm-history-label {
            color: color-mix(in oklab, white 70%, transparent);
        }

        .nm-history .nm-heartbeat,
        .nm-history .nm-latency {
            width: 100%;
            min-width: 0;
        }

        .nm-history .nm-heartbeat {
            height: 1.75rem;
        }

        .nm-history .nm-latency {
            height: 4.5rem;
        }

        .nm-heatmap {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .nm-heatmap-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nm-heatmap-label {
            width: 3.25rem;
            flex: none;
            font-size: 0.7rem;
            color: color-mix(in oklab, var(--gray-950, #18181b) 55%, transparent);
        }

        .dark .nm-heatmap-label {
            color: color-mix(in oklab, white 55%, transparent);
        }

        .nm-heatmap-hours {
            display: flex;
            flex: 1;
            gap: 2px;
        }

        .nm-heatmap-hours span {
            flex: 1 1 0;
            height: 12px;
            min-width: 4px;
            border-radius: 2px;
        }

        .nm-heatmap [data-hour="up"] { background: #10b981; }
        .nm-heatmap [data-hour="down"] { background: #f43f5e; }
        .nm-heatmap [data-hour="mixed"] { background: #f59e0b; }
        .nm-heatmap [data-hour="empty"] { background: #e4e4e7; }
        .dark .nm-heatmap [data-hour="empty"] { background: #3f3f46; }
    </style>
@endonce
