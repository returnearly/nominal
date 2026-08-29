@once
    <style>
        :root {
            --nm-cream: #fdfff8;
            --nm-mint: #f3f7eb;
            --nm-sage: #e0eadd;
            --nm-sage-strong: #d1decd;
            --nm-ink: #151414;
            --nm-ink-soft: #272c2b;
            --nm-celadon: #5adeb7;
            --nm-celadon-mid: #95e6cd;
            --nm-celadon-soft: #e0f8f2;
            --nm-mist: #b5c4f5;
            --nm-void: #030a08;
            --nm-forest: #0c1613;
            --nm-grid: 3.5rem;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body.nm-status {
            color: var(--nm-ink);
            background-color: var(--nm-cream);
            background-image:
                linear-gradient(color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px);
            background-size: var(--nm-grid) var(--nm-grid);
            background-attachment: fixed;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            line-height: 1.5;
        }

        body.nm-status.dark {
            color: var(--nm-cream);
            background-color: var(--nm-void);
            background-image:
                linear-gradient(color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px),
                radial-gradient(ellipse 75% 55% at 50% 28%, color-mix(in oklab, var(--nm-celadon) 7%, transparent), transparent 70%);
            background-size: var(--nm-grid) var(--nm-grid), var(--nm-grid) var(--nm-grid), 100% 100%;
        }

        .nm-status-shell {
            width: min(72rem, calc(100% - 2rem));
            margin: 0 auto;
            padding: 1.75rem 0 3.5rem;
        }

        .nm-status-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .nm-status-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            min-width: 0;
            color: inherit;
            text-decoration: none;
        }

        .nm-status-logo {
            display: block;
            height: 2.4rem;
            width: auto;
            max-width: 11rem;
            object-fit: contain;
        }

        .nm-status-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 650;
            letter-spacing: -0.03em;
        }

        .nm-status-kicker {
            margin: 0.15rem 0 0;
            font-size: 0.82rem;
            color: color-mix(in oklab, var(--nm-ink) 55%, transparent);
        }

        body.nm-status.dark .nm-status-kicker {
            color: color-mix(in oklab, var(--nm-cream) 55%, transparent);
        }

        .nm-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem 1.15rem;
            border-radius: 1rem;
            font-weight: 650;
            letter-spacing: -0.02em;
        }

        .nm-banner[data-health="operational"] {
            color: #151414;
            background: #5adeb7;
        }

        .nm-banner[data-health="degraded"],
        .nm-banner[data-health="partial_outage"] {
            color: #151414;
            background: #dfc331;
        }

        .nm-banner[data-health="major_outage"] {
            color: #fdfff8;
            background: #d15c5c;
        }

        .nm-banner[data-health="maintenance"] {
            color: #151414;
            background: #98a5ef;
        }

        .nm-banner-time {
            font-size: 0.78rem;
            font-weight: 500;
            opacity: 0.8;
        }

        .nm-status-copy {
            max-width: 42rem;
            margin: 0 0 1.5rem;
            color: color-mix(in oklab, var(--nm-ink) 70%, transparent);
        }

        body.nm-status.dark .nm-status-copy {
            color: color-mix(in oklab, var(--nm-cream) 70%, transparent);
        }

        .nm-group {
            margin-bottom: 1.75rem;
        }

        .nm-group-title {
            margin: 0 0 0.75rem;
            font-size: 0.82rem;
            font-weight: 650;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: color-mix(in oklab, var(--nm-ink) 55%, transparent);
        }

        body.nm-status.dark .nm-group-title {
            color: color-mix(in oklab, var(--nm-cream) 55%, transparent);
        }

        .nm-status-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.85rem;
        }

        @media (min-width: 768px) {
            .nm-status-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1200px) {
            .nm-status-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .nm-status-tile {
            padding: 1rem 1.1rem 1.05rem;
            border: 1px solid var(--nm-sage);
            border-radius: 1rem;
            background: #fff;
        }

        body.nm-status.dark .nm-status-tile {
            border-color: color-mix(in oklab, var(--nm-celadon) 14%, transparent);
            background: var(--nm-forest);
        }

        .nm-incident {
            display: block;
            margin-bottom: 0.85rem;
            padding: 1rem 1.1rem;
            border: 1px solid var(--nm-sage);
            border-radius: 1rem;
            background: #fff;
            color: inherit;
            text-decoration: none;
        }

        body.nm-status.dark .nm-incident {
            border-color: color-mix(in oklab, var(--nm-celadon) 14%, transparent);
            background: var(--nm-forest);
        }

        .nm-incident-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .nm-incident-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 650;
        }

        .nm-incident-meta {
            margin: 0.35rem 0 0;
            font-size: 0.8rem;
            color: color-mix(in oklab, var(--nm-ink) 55%, transparent);
        }

        body.nm-status.dark .nm-incident-meta {
            color: color-mix(in oklab, var(--nm-cream) 55%, transparent);
        }

        .nm-incident-message {
            margin: 0.7rem 0 0;
            white-space: pre-wrap;
        }

        .nm-timeline {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            margin-top: 1.1rem;
        }

        .nm-update {
            padding-left: 0.9rem;
            border-left: 2px solid var(--nm-sage-strong);
        }

        body.nm-status.dark .nm-update {
            border-left-color: color-mix(in oklab, var(--nm-celadon) 24%, transparent);
        }

        .nm-status-footer {
            margin-top: 2.5rem;
            font-size: 0.78rem;
            color: color-mix(in oklab, var(--nm-ink) 50%, transparent);
        }

        body.nm-status.dark .nm-status-footer {
            color: color-mix(in oklab, var(--nm-cream) 50%, transparent);
        }

        .nm-password {
            width: min(24rem, calc(100% - 2rem));
            margin: 18vh auto 0;
            padding: 1.4rem 1.35rem 1.5rem;
            border: 1px solid var(--nm-sage);
            border-radius: 1.1rem;
            background: #fff;
        }

        body.nm-status.dark .nm-password {
            border-color: color-mix(in oklab, var(--nm-celadon) 14%, transparent);
            background: var(--nm-forest);
        }

        .nm-password h1 {
            margin: 0 0 0.4rem;
            font-size: 1.2rem;
        }

        .nm-password p {
            margin: 0 0 1rem;
            font-size: 0.9rem;
        }

        .nm-password input {
            width: 100%;
            margin-bottom: 0.85rem;
            padding: 0.7rem 0.8rem;
            border: 1px solid var(--nm-sage-strong);
            border-radius: 0.6rem;
            background: #fff;
            color: inherit;
        }

        body.nm-status.dark .nm-password input {
            border-color: color-mix(in oklab, var(--nm-celadon) 16%, transparent);
            background: #08110e;
        }

        .nm-password button {
            width: 100%;
            padding: 0.7rem 0.8rem;
            border: 0;
            border-radius: 0.6rem;
            background: var(--nm-celadon);
            color: var(--nm-ink);
            font-weight: 650;
            cursor: pointer;
        }

        .nm-password-error {
            margin: 0 0 0.8rem;
            color: #d15c5c;
            font-size: 0.85rem;
        }

        .nm-back {
            display: inline-block;
            margin-bottom: 1rem;
            color: inherit;
            font-size: 0.85rem;
        }

        .nm-status-badge[data-status="investigating"] {
            color: #fff;
            background: #d15c5c;
        }

        .nm-status-badge[data-status="identified"] {
            color: #151414;
            background: #dfc331;
        }

        .nm-status-badge[data-status="monitoring"] {
            color: #fdfff8;
            background: #485fde;
        }

        .nm-status-badge[data-status="resolved"] {
            color: #151414;
            background: #5adeb7;
        }

        .nm-status-badge[data-status="scheduled"] {
            color: #151414;
            background: #98a5ef;
        }
    </style>
@endonce
