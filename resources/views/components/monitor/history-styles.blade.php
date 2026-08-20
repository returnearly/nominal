@once
    <style>
        .nm-heartbeat,
        .nm-latency {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            width: 100%;
            min-width: 0;
        }

        .nm-heartbeat {
            height: 2rem;
        }

        .nm-latency {
            height: 4.5rem;
        }

        .nm-heartbeat > span,
        .nm-latency > span {
            position: relative;
            flex: 1 1 0;
            min-width: 2px;
            height: 100%;
            border-radius: 3px;
            transition: filter 120ms ease, transform 120ms ease;
        }

        .nm-latency > span {
            height: auto;
        }

        .nm-latency > span.nm-beat-empty {
            height: 20%;
        }

        .nm-heartbeat > span:hover:not(.nm-beat-empty),
        .nm-latency > span:hover:not(.nm-beat-empty) {
            z-index: 20;
            filter: brightness(1.18);
            transform: scaleY(1.12);
        }

        .nm-beat-up {
            background: #4fcba6;
        }

        .nm-beat-down {
            background: #d15c5c;
        }

        .nm-beat-empty {
            background: transparent;
            box-shadow: inset 0 0 0 1px color-mix(in oklab, #151414 22%, transparent);
        }

        .dark .nm-beat-empty {
            background: transparent;
            box-shadow: inset 0 0 0 1px color-mix(in oklab, #fdfff8 28%, transparent);
        }

        .dark .nm-beat-up {
            background: #5adeb7;
        }

        .nm-beat-tip {
            display: none;
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            z-index: 30;
            width: max-content;
            max-width: 16rem;
            padding: 0.55rem 0.7rem;
            transform: translateX(-50%);
            border-radius: 0.5rem;
            background: #151414;
            color: #fdfff8;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.68rem;
            line-height: 1.45;
            pointer-events: none;
            box-shadow: 0 10px 30px rgb(0 0 0 / 0.28);
        }

        .nm-heartbeat > span:first-child .nm-beat-tip,
        .nm-latency > span:first-child .nm-beat-tip {
            left: 0;
            transform: none;
        }

        .nm-heartbeat > span:last-child .nm-beat-tip,
        .nm-latency > span:last-child .nm-beat-tip {
            left: auto;
            right: 0;
            transform: none;
        }

        .nm-heartbeat > span:hover .nm-beat-tip,
        .nm-latency > span:hover .nm-beat-tip {
            display: grid;
            gap: 0.1rem;
        }

        .nm-beat-tip-k {
            margin-top: 0.35rem;
            color: #a1a1aa;
            font-size: 0.58rem;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        .nm-beat-tip-k:first-child {
            margin-top: 0;
        }

        .nm-range {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 0.4rem;
            font-size: 0.7rem;
            color: color-mix(in oklab, var(--gray-950, #18181b) 55%, transparent);
        }

        .dark .nm-range {
            color: color-mix(in oklab, white 55%, transparent);
        }

        .nm-card {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            width: 100%;
            min-width: 0;
            overflow: visible;
        }

        .nm-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .nm-card-copy {
            min-width: 0;
        }

        .nm-card-name {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 650;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nm-card-maintenance {
            margin: 0.35rem 0 0;
            font-size: 0.75rem;
            line-height: 1.35;
            color: #a16207;
        }

        .dark .nm-card-maintenance {
            color: #dfc331;
        }

        .nm-card-meta {
            margin: 0.2rem 0 0;
            font-size: 0.75rem;
            line-height: 1.35;
            color: color-mix(in oklab, var(--gray-950, #18181b) 55%, transparent);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .nm-card-meta {
            color: color-mix(in oklab, white 55%, transparent);
        }

        .nm-card-dot {
            margin: 0 0.2rem;
        }

        .nm-card-chart {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .nm-card-chart .nm-heartbeat {
            height: 1.05rem;
            gap: 2px;
        }

        .nm-card-chart .nm-heartbeat > span {
            border-radius: 2px;
        }

        .nm-card-chart .nm-range {
            margin-top: 0.35rem;
        }

        .nm-card-latency {
            flex: none;
            margin: 0;
            min-width: 3.1rem;
            font-size: 0.75rem;
            text-align: right;
            font-variant-numeric: tabular-nums;
            color: color-mix(in oklab, var(--gray-950, #18181b) 55%, transparent);
        }

        .dark .nm-card-latency {
            color: color-mix(in oklab, white 55%, transparent);
        }

        .nm-status-badge {
            display: inline-flex;
            flex: none;
            align-items: center;
            gap: 0.4rem;
            padding: 0.22rem 0.7rem 0.22rem 0.55rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 650;
            letter-spacing: 0.01em;
            white-space: nowrap;
        }

        .nm-status-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: #fff;
        }

        .nm-status-badge[data-status="up"] .nm-status-dot,
        .nm-status-badge[data-status="paused"] .nm-status-dot,
        .nm-status-badge[data-status="maintenance"] .nm-status-dot {
            background: #151414;
        }

        .nm-status-badge[data-status="up"] {
            color: #151414;
            background: #5adeb7;
        }

        .nm-status-badge[data-status="down"] {
            color: #fff;
            background: #d15c5c;
        }

        .nm-status-badge[data-status="pending"],
        .nm-status-badge[data-status="disabled"] {
            color: #5a5a5a;
            background: #e0eadd;
        }

        .nm-status-badge[data-status="pending"] .nm-status-dot,
        .nm-status-badge[data-status="disabled"] .nm-status-dot {
            background: currentColor;
        }

        .nm-status-badge[data-status="paused"] {
            color: #151414;
            background: #b5c4f5;
        }

        .nm-status-badge[data-status="maintenance"] {
            color: #151414;
            background: #dfc331;
        }

        .dark .nm-status-badge[data-status="up"] {
            color: #151414;
            background: #5adeb7;
        }

        .dark .nm-status-badge[data-status="down"] {
            color: #fdfff8;
            background: #d15c5c;
        }

        .dark .nm-status-badge[data-status="pending"],
        .dark .nm-status-badge[data-status="disabled"] {
            color: #e0eadd;
            background: color-mix(in oklab, #d1decd 22%, #151414);
        }

        .dark .nm-status-badge[data-status="paused"] {
            color: #151414;
            background: #98a5ef;
        }

        .dark .nm-status-badge[data-status="maintenance"] {
            color: #151414;
            background: #dfc331;
        }

        .nm-history,
        .nm-detail {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            overflow: visible;
        }

        .nm-panel {
            overflow: visible;
            padding: 1rem 1.1rem 1.15rem;
            border: 1px solid #e0eadd;
            border-radius: 0.9rem;
            background: #fff;
        }

        .dark .nm-panel {
            border-color: color-mix(in oklab, #5adeb7 14%, transparent);
            background: #0c1613;
        }

        .nm-stat-cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        @media (min-width: 768px) {
            .nm-stat-cards {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .nm-stat-card {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 0;
        }

        .nm-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
        }

        .nm-section-title {
            margin: 0 0 0.15rem;
            font-size: 0.92rem;
            font-weight: 650;
        }

        .nm-section-head .nm-card-latency {
            margin: 0.15rem 0 0;
        }

        .nm-panel > .nm-section-title {
            margin-bottom: 0.85rem;
        }

        .nm-trend {
            display: grid;
            grid-template-columns: 3.25rem minmax(0, 1fr);
            grid-template-rows: 10rem auto;
            column-gap: 0.55rem;
            width: 100%;
            overflow: visible;
        }

        .nm-trend-y {
            position: relative;
            grid-row: 1;
            grid-column: 1;
        }

        .nm-trend-y span {
            position: absolute;
            right: 0;
            transform: translateY(-50%);
            font-size: 0.7rem;
            font-variant-numeric: tabular-nums;
            color: color-mix(in oklab, var(--gray-950, #18181b) 50%, transparent);
        }

        .nm-trend-y span:first-child {
            transform: none;
        }

        .nm-trend-y span:last-child {
            transform: translateY(-100%);
        }

        .dark .nm-trend-y span {
            color: color-mix(in oklab, white 50%, transparent);
        }

        .nm-trend-body {
            position: relative;
            grid-row: 1;
            grid-column: 2;
            min-width: 0;
            height: 10rem;
            overflow: visible;
        }

        .nm-trend-svg {
            display: block;
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .nm-trend-x {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            grid-column: 2;
            margin-top: 0.4rem;
            font-size: 0.7rem;
            color: color-mix(in oklab, var(--gray-950, #18181b) 50%, transparent);
        }

        .dark .nm-trend-x {
            color: color-mix(in oklab, white 50%, transparent);
        }

        .nm-trend-grid {
            stroke: color-mix(in oklab, var(--gray-950, #18181b) 12%, transparent);
            stroke-width: 1;
            vector-effect: non-scaling-stroke;
        }

        .dark .nm-trend-grid {
            stroke: color-mix(in oklab, white 12%, transparent);
        }

        .nm-trend-line {
            fill: none;
            stroke: #4fcba6;
            stroke-width: 1.75;
            stroke-linejoin: round;
            stroke-linecap: round;
            vector-effect: non-scaling-stroke;
        }

        .nm-trend-fill {
            fill: color-mix(in oklab, #5adeb7 22%, transparent);
        }

        .nm-trend-hits {
            position: absolute;
            inset: 0;
            overflow: visible;
        }

        .nm-trend-hit {
            position: absolute;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.25rem;
            height: 1.25rem;
            transform: translate(-50%, -50%);
        }

        .nm-trend-hit:hover {
            z-index: 20;
        }

        .nm-trend-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: #4fcba6;
            box-shadow: 0 0 0 3px color-mix(in oklab, #5adeb7 24%, transparent);
            transition: transform 120ms ease;
        }

        .nm-trend-hit-down .nm-trend-dot {
            background: #d15c5c;
            box-shadow: 0 0 0 3px color-mix(in oklab, #d15c5c 24%, transparent);
        }

        .nm-trend-hit:hover .nm-trend-dot {
            transform: scale(1.45);
        }

        .nm-trend-hit:hover .nm-beat-tip {
            display: grid;
            gap: 0.1rem;
        }

        .nm-trend-hit:first-child .nm-beat-tip {
            left: 0;
            transform: none;
        }

        .nm-trend-hit:last-child .nm-beat-tip {
            left: auto;
            right: 0;
            transform: none;
        }

        .nm-empty {
            margin: 0;
            font-size: 0.85rem;
            color: color-mix(in oklab, var(--gray-950, #18181b) 55%, transparent);
        }

        .dark .nm-empty {
            color: color-mix(in oklab, white 55%, transparent);
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

        .nm-detail-interval {
            margin: 0.45rem 0 0;
            font-size: 0.75rem;
            color: color-mix(in oklab, var(--gray-950, #18181b) 55%, transparent);
        }

        .dark .nm-detail-interval {
            color: color-mix(in oklab, white 55%, transparent);
        }

        .nm-metrics-label {
            display: block;
            margin-bottom: 0.2rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: color-mix(in oklab, var(--gray-950, #18181b) 55%, transparent);
        }

        .dark .nm-metrics-label {
            color: color-mix(in oklab, white 55%, transparent);
        }

        .nm-metrics-value {
            font-size: 1.05rem;
            font-weight: 650;
            font-variant-numeric: tabular-nums;
        }

        .nm-copy-url {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .nm-copy-url-value {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.82rem;
        }

        .nm-copy-url-button {
            flex: none;
            padding: 0.25rem 0.65rem;
            border: 1px solid color-mix(in oklab, var(--gray-950, #18181b) 12%, transparent);
            border-radius: 0.45rem;
            background: transparent;
            color: inherit;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
        }

        .dark .nm-copy-url-button {
            border-color: color-mix(in oklab, white 14%, transparent);
        }
    </style>
@endonce
