<!DOCTYPE html>
<html lang="en" x-data="{ dark: localStorage.getItem('theme') !== 'light' }"
      x-init="$watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light') })"
      :class="dark ? 'dark' : ''" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Spider</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: {
                    sans: ['"DM Sans"', 'system-ui', 'sans-serif'],
                    mono: ['"JetBrains Mono"', 'Consolas', 'monospace'],
                },
                fontSize: {
                    '2xs': ['0.6875rem', { lineHeight: '1rem' }],
                },
            }
        }
    }
    </script>
    <style>
        :root {
            --c-bg:       #ffffff; --c-bg2:      #f7f7f8; --c-bg3:      #efefef;
            --c-surface:  #ffffff; --c-surface2: #f4f4f5; --c-surface3: #e8e8ec;
            --c-border:   #e0e0e4; --c-border2:  #d0d0d6;
            --c-fg:       #1a1a1f; --c-fg2:      #4a4a58; --c-fg3:      #78788a; --c-fg4: #a0a0b2;
            --c-accent:   #2563eb; --c-accent-h: #1d4ed8; --c-accent-bg: rgba(37,99,235,0.07);
            --c-ok:       #16a34a; --c-ok-bg:    rgba(22,163,74,0.08);
            --c-warn:     #d97706; --c-warn-bg:  rgba(217,119,6,0.08);
            --c-err:      #dc2626; --c-err-bg:   rgba(220,38,38,0.07);
            --c-info:     #7c3aed; --c-info-bg:  rgba(124,58,237,0.07);
            --c-row-hover: rgba(0,0,0,0.02); --c-row-sel: rgba(37,99,235,0.06);
        }
        .dark {
            --c-bg:       #0a0a0c; --c-bg2:      #101014; --c-bg3:      #1a1a20;
            --c-surface:  #111116; --c-surface2: #19191f; --c-surface3: #222230;
            --c-border:   rgba(255,255,255,0.07); --c-border2: rgba(255,255,255,0.12);
            --c-fg:       #ededf0; --c-fg2:      #a0a0b0; --c-fg3:      #6b6b80; --c-fg4: #4a4a5e;
            --c-accent:   #5b8af5; --c-accent-h: #7ba3ff; --c-accent-bg: rgba(91,138,245,0.1);
            --c-ok:       #34d399; --c-ok-bg:    rgba(52,211,153,0.08);
            --c-warn:     #fbbf24; --c-warn-bg:  rgba(251,191,36,0.08);
            --c-err:      #f87171; --c-err-bg:   rgba(248,113,113,0.08);
            --c-info:     #a78bfa; --c-info-bg:  rgba(167,139,250,0.08);
            --c-row-hover: rgba(255,255,255,0.015); --c-row-sel: rgba(91,138,245,0.07);
        }

        body { font-family: 'DM Sans', system-ui, sans-serif; -webkit-font-smoothing: antialiased;
               background: var(--c-bg); color: var(--c-fg); }
        .font-mono { font-family: 'JetBrains Mono', Consolas, monospace; }

        /* Utility color classes via custom properties */
        .bg-app       { background: var(--c-bg); }
        .bg-app2      { background: var(--c-bg2); }
        .bg-app3      { background: var(--c-bg3); }
        .bg-panel     { background: var(--c-surface); }
        .bg-panel2    { background: var(--c-surface2); }
        .bg-panel3    { background: var(--c-surface3); }
        .border-line  { border-color: var(--c-border); }
        .border-line2 { border-color: var(--c-border2); }
        .text-primary   { color: var(--c-fg); }
        .text-secondary { color: var(--c-fg2); }
        .text-tertiary  { color: var(--c-fg3); }
        .text-muted     { color: var(--c-fg4); }
        .text-link      { color: var(--c-accent); }
        .bg-accent-s  { background: var(--c-accent-bg); }
        .bg-ok-s      { background: var(--c-ok-bg); }
        .bg-warn-s    { background: var(--c-warn-bg); }
        .bg-err-s     { background: var(--c-err-bg); }
        .bg-info-s    { background: var(--c-info-bg); }
        .c-accent     { color: var(--c-accent); }
        .c-ok         { color: var(--c-ok); }
        .c-warn       { color: var(--c-warn); }
        .c-err        { color: var(--c-err); }
        .c-info       { color: var(--c-info); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--c-border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--c-border2); }
        ::-webkit-scrollbar-corner { background: transparent; }

        /* Animations */
        @keyframes fade-in { from { opacity:0; transform:translateY(3px) } to { opacity:1; transform:translateY(0) } }
        @keyframes slide-up { from { opacity:0; transform:translateY(6px) } to { opacity:1; transform:translateY(0) } }
        @keyframes pulse-dot { 0%,100% { opacity:1 } 50% { opacity:.3 } }
        .anim-fade   { animation: fade-in 0.25s ease-out both; }
        .anim-slide  { animation: slide-up 0.3s ease-out both; }
        .dot-pulse   { animation: pulse-dot 1.4s ease-in-out infinite; }

        /* Table rows */
        .row-hover:hover  { background: var(--c-row-hover); }
        .row-selected     { background: var(--c-row-sel) !important; }
        tbody tr          { transition: background 0.1s ease; }

        /* Badge */
        .badge {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: 11px; font-weight: 500; padding: 1px 7px; border-radius: 4px;
            line-height: 1.4;
        }
        .badge-ok   { background: var(--c-ok-bg);   color: var(--c-ok); }
        .badge-warn { background: var(--c-warn-bg); color: var(--c-warn); }
        .badge-err  { background: var(--c-err-bg);  color: var(--c-err); }
        .badge-info { background: var(--c-info-bg); color: var(--c-info); }

        /* Stat card */
        .stat-card { transition: all 0.15s ease; }
        .stat-card:hover { border-color: var(--c-border2); }

        /* Progress bar smooth */
        .progress-fill { transition: width 0.8s cubic-bezier(0.4,0,0.2,1); }

        /* Input */
        input:focus { outline: none; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
    @livewireStyles
</head>
<body class="h-full overflow-hidden">
    {{ $slot }}
    @livewireScripts
</body>
</html>
