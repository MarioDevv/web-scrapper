<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>SEO Spider</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans:    ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
                    mono:    ['"JetBrains Mono"', '"IBM Plex Mono"', 'ui-monospace', 'monospace'],
                    display: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
                },
                fontSize: {
                    '3xs': ['0.625rem',  { lineHeight: '0.875rem' }],
                    '2xs': ['0.6875rem', { lineHeight: '1rem' }],
                },
            }
        }
    }
    </script>
    <style>
        :root {
            color-scheme: dark;

            /* ── Surfaces (near-black with green undertone) ── */
            --c-bg:       #0a0c0a;
            --c-bg2:      #0d100d;
            --c-bg3:      #111511;
            --c-surface:  #0f1310;
            --c-surface2: #131812;
            --c-surface3: #1a2018;

            /* ── Borders (lime ghost) ── */
            --c-border:   rgba(166,226,46,0.07);
            --c-border2:  rgba(166,226,46,0.16);
            --c-border3:  rgba(166,226,46,0.32);

            /* ── Text (phosphor white) ── */
            --c-fg:   #dff0c8;
            --c-fg2:  #a3b09a;
            --c-fg3:  #6b7a66;
            --c-fg4:  #4a544a;

            /* ── Accent: lime phosphor ── */
            --c-accent:      #a6e22e;
            --c-accent-h:    #c6ff5c;
            --c-accent-bg:   rgba(166,226,46,0.10);
            --c-accent-glow: rgba(166,226,46,0.40);

            /* ── Signals ── */
            --c-ok:   #a6e22e;  --c-ok-bg:   rgba(166,226,46,0.10);
            --c-warn: #f5a524;  --c-warn-bg: rgba(245,165,36,0.10);
            --c-err:  #ff5470;  --c-err-bg:  rgba(255,84,112,0.10);
            --c-info: #7dd3fc;  --c-info-bg: rgba(125,211,252,0.10);

            /* ── Table row states ── */
            --c-row-hover: rgba(166,226,46,0.04);
            --c-row-sel:   rgba(166,226,46,0.09);
        }

        html, body {
            overscroll-behavior: none;
        }

        body {
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-feature-settings: "ss01", "cv01";
            -webkit-font-smoothing: antialiased;
            background: var(--c-bg);
            color: var(--c-fg);
            letter-spacing: -0.005em;
        }

        .font-mono {
            font-family: 'JetBrains Mono', 'IBM Plex Mono', ui-monospace, monospace;
        }

        /* ── Surfaces ── */
        .bg-app    { background: var(--c-bg); }
        .bg-app2   { background: var(--c-bg2); }
        .bg-app3   { background: var(--c-bg3); }
        .bg-panel  { background: var(--c-surface); }
        .bg-panel2 { background: var(--c-surface2); }
        .bg-panel3 { background: var(--c-surface3); }
        .border-line  { border-color: var(--c-border); }
        .border-line2 { border-color: var(--c-border2); }
        .border-line3 { border-color: var(--c-border3); }

        /* ── Text ── */
        .text-primary   { color: var(--c-fg); }
        .text-secondary { color: var(--c-fg2); }
        .text-tertiary  { color: var(--c-fg3); }
        .text-muted     { color: var(--c-fg4); }
        .text-link      { color: var(--c-accent); }

        /* ── Signal tints ── */
        .bg-accent-s { background: var(--c-accent-bg); }
        .bg-ok-s     { background: var(--c-ok-bg); }
        .bg-warn-s   { background: var(--c-warn-bg); }
        .bg-err-s    { background: var(--c-err-bg); }
        .bg-info-s   { background: var(--c-info-bg); }
        .c-accent { color: var(--c-accent); }
        .c-ok     { color: var(--c-ok); }
        .c-warn   { color: var(--c-warn); }
        .c-err    { color: var(--c-err); }
        .c-info   { color: var(--c-info); }

        /* ── Ambient grid (subtle operator feel) ── */
        .bg-grid {
            background-image:
              linear-gradient(rgba(166,226,46,0.025) 1px, transparent 1px),
              linear-gradient(90deg, rgba(166,226,46,0.025) 1px, transparent 1px);
            background-size: 32px 32px;
            background-position: -1px -1px;
        }

        /* ── Scrollbar: phosphor ── */
        ::-webkit-scrollbar         { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track   { background: transparent; }
        ::-webkit-scrollbar-thumb   { background: var(--c-border2); border-radius: 0; }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--c-accent);
            box-shadow: 0 0 8px var(--c-accent-glow);
        }
        ::-webkit-scrollbar-corner  { background: transparent; }

        /* ── Text selection ── */
        ::selection { background: var(--c-accent); color: #0a0c0a; }

        /* ── NativePHP window-drag regions ── */
        .app-drag    { -webkit-app-region: drag; }
        .app-no-drag { -webkit-app-region: no-drag; }

        /* ── Chrome: no text selection in app frame ── */
        .chrome-nosel {
            user-select: none;
            -webkit-user-select: none;
            cursor: default;
        }

        /* ── Keyframes ── */
        @keyframes cursor-blink {
            0%, 49%    { opacity: 1; }
            50%, 100%  { opacity: 0; }
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.35; transform: scale(0.8); }
        }
        @keyframes scan-sweep {
            0%   { transform: translateX(-120%); }
            100% { transform: translateX(320%); }
        }
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(2px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes slide-up {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes ellipsis-clip {
            0%       { clip-path: inset(0 100% 0 0); }
            25%      { clip-path: inset(0 66%  0 0); }
            50%      { clip-path: inset(0 33%  0 0); }
            75%,100% { clip-path: inset(0 0    0 0); }
        }
        @keyframes flicker {
            0%, 92%, 100% { opacity: 1; }
            93%           { opacity: 0.72; }
            94%           { opacity: 1; }
            96%           { opacity: 0.85; }
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes row-new {
            0%   { background: rgba(166,226,46,0.22); box-shadow: inset 2px 0 0 var(--c-accent); }
            60%  { background: rgba(166,226,46,0.07); box-shadow: inset 2px 0 0 rgba(166,226,46,0.45); }
            100% { background: transparent;           box-shadow: inset 2px 0 0 transparent; }
        }

        /* ── Animation utilities ── */
        .cursor-blink::after {
            content: "▍";
            color: var(--c-accent);
            animation: cursor-blink 1.06s steps(1,end) infinite;
            margin-left: 1px;
            text-shadow: 0 0 6px var(--c-accent-glow);
        }
        .dot-pulse { animation: pulse-dot 1.2s ease-in-out infinite; }
        .ellipsis  { display: inline-flex; align-items: baseline; }
        .ellipsis::after {
            content: "...";
            display: inline-block;
            animation: ellipsis-clip 1.4s steps(4) infinite;
            clip-path: inset(0 100% 0 0);
            margin-left: 1px;
        }
        .anim-fade  { animation: fade-in 0.2s ease-out both; }
        .anim-slide { animation: slide-up 0.24s cubic-bezier(0.2,0,0.2,1) both; }
        .flicker    { animation: flicker 6s infinite; }
        .animate-spin { animation: spin 0.9s linear infinite; }

        /* ── Progress track / fill with scan sweep ── */
        .progress-track {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,0.05);
        }
        .progress-fill {
            position: relative;
            height: 100%;
            transition: width 0.8s cubic-bezier(0.4,0,0.2,1);
        }
        .progress-fill.is-active::after {
            content: "";
            position: absolute;
            inset: 0;
            width: 40%;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255,255,255,0.55) 50%,
                transparent 100%);
            animation: scan-sweep 1.8s linear infinite;
            pointer-events: none;
        }

        /* ── Table rows ── */
        .row-hover:hover { background: var(--c-row-hover); }
        .row-selected    { background: var(--c-row-sel) !important; }
        tbody tr         { transition: background 0.08s ease; }
        .row-new         { animation: row-new 900ms ease-out forwards; }

        /* ── Badge (terminal-tag style) ── */
        .badge {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: 10px; font-weight: 500; padding: 1px 6px;
            font-family: 'JetBrains Mono', 'IBM Plex Mono', ui-monospace, monospace;
            line-height: 1.4;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1px solid currentColor;
            background: transparent;
        }
        .badge-ok   { color: var(--c-ok); }
        .badge-warn { color: var(--c-warn); }
        .badge-err  { color: var(--c-err); }
        .badge-info { color: var(--c-info); }

        /* ── Stat card (legacy hook) ── */
        .stat-card { transition: border-color 0.15s ease; }
        .stat-card:hover { border-color: var(--c-border2); }

        /* ── Forms ── */
        input:focus { outline: none; }
        .tabular-nums { font-variant-numeric: tabular-nums; }

        /* ── Alpine cloak ── */
        [x-cloak] { display: none !important; }
        .peer:checked ~ div svg { opacity: 1; }

        /* ── Reduced motion ── */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            .cursor-blink::after { animation: none; opacity: 1; }
            .ellipsis::after     { animation: none; clip-path: none; }
            .progress-fill.is-active::after { animation: none; opacity: 0; }
            .row-new             { animation: none; }
        }
    </style>
    @livewireStyles
</head>
<body class="h-full overflow-hidden bg-app bg-grid">
    {{ $slot }}
    @livewireScripts
</body>
</html>
