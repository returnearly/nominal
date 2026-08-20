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
            --nm-down: #e11d1d;
            --nm-mist: #b5c4f5;
            --nm-void: #030a08;
            --nm-forest: #0c1613;
            --nm-grid: 3.5rem;
        }

        ::selection {
            color: var(--nm-ink);
            background: var(--nm-celadon-mid);
        }

        .fi-body {
            color: var(--nm-ink);
            background-color: var(--nm-cream);
            background-image:
                linear-gradient(color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px);
            background-size: var(--nm-grid) var(--nm-grid);
            background-attachment: fixed;
        }

        .dark .fi-body {
            color: var(--nm-cream);
            background-color: var(--nm-void);
            background-image:
                linear-gradient(color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in oklab, var(--nm-celadon) 7%, transparent) 1px, transparent 1px),
                radial-gradient(ellipse 75% 55% at 50% 28%, color-mix(in oklab, var(--nm-celadon) 7%, transparent), transparent 70%);
            background-size: var(--nm-grid) var(--nm-grid), var(--nm-grid) var(--nm-grid), 100% 100%;
        }

        .fi-layout,
        .fi-main,
        .fi-page,
        .fi-simple-layout,
        .fi-simple-main,
        .fi-simple-main-ctn {
            background: transparent;
        }

        .fi-topbar,
        .fi-simple-header {
            background: var(--nm-cream);
            border-bottom: 1px solid var(--nm-sage);
        }

        .dark .fi-topbar,
        .dark .fi-simple-header {
            background: color-mix(in oklab, var(--nm-void) 88%, black);
            border-bottom-color: color-mix(in oklab, var(--nm-celadon) 14%, transparent);
        }

        .fi-topbar-item-btn[data-active],
        .fi-topbar-item-btn[aria-current="page"] {
            background: var(--nm-celadon-soft);
            color: var(--nm-ink);
        }

        .dark .fi-topbar-item-btn[data-active],
        .dark .fi-topbar-item-btn[aria-current="page"] {
            background: color-mix(in oklab, var(--nm-celadon) 18%, var(--nm-ink-soft));
            color: var(--nm-cream);
        }

        .fi-section,
        .fi-ta-ctn,
        .fi-wi-widget,
        .fi-modal-window,
        .fi-dropdown-panel,
        .fi-simple-card,
        .fi-sc-section {
            background: #fff;
            border-color: var(--nm-sage);
        }

        .dark .fi-section,
        .dark .fi-ta-ctn,
        .dark .fi-wi-widget,
        .dark .fi-modal-window,
        .dark .fi-dropdown-panel,
        .dark .fi-simple-card,
        .dark .fi-sc-section {
            background: var(--nm-forest);
            border-color: color-mix(in oklab, var(--nm-celadon) 14%, transparent);
        }

        .fi-ta-content-grid .fi-ta-record {
            background: #fff;
            border: 1px solid var(--nm-sage);
            border-radius: 1rem;
        }

        .dark .fi-ta-content-grid .fi-ta-record {
            background: var(--nm-forest);
            border-color: color-mix(in oklab, var(--nm-celadon) 14%, transparent);
        }

        .fi-input-wrp,
        .fi-select-wrp,
        .fi-fo-textarea {
            background: #fff;
            border-color: var(--nm-sage-strong);
        }

        .dark .fi-input-wrp,
        .dark .fi-select-wrp,
        .dark .fi-fo-textarea {
            background: #08110e;
            border-color: color-mix(in oklab, var(--nm-celadon) 16%, transparent);
        }

        .fi-simple-header .fi-logo {
            height: 2.75rem !important;
        }

        html.nm-monitors-down .nm-logo-bg {
            fill: var(--nm-down);
        }
    </style>
@endonce
