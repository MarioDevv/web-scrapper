<!DOCTYPE html>
<html lang="en" class="dark h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Spider</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bg:       { DEFAULT: '#09090b', 2: '#0f0f12', 3: '#18181b' },
                        surface:  { DEFAULT: '#131316', 2: '#1a1a1f', 3: '#222228' },
                        border:   { DEFAULT: '#27272a', 2: '#3f3f46' },
                        fg:       { DEFAULT: '#fafafa', 2: '#a1a1aa', 3: '#71717a', 4: '#52525b' },
                        accent:   { DEFAULT: '#3b82f6', dim: '#1d4ed8', glow: 'rgba(59,130,246,0.15)' },
                        ok:       { DEFAULT: '#22c55e', dim: '#166534', bg: 'rgba(34,197,94,0.1)' },
                        warn:     { DEFAULT: '#f59e0b', dim: '#92400e', bg: 'rgba(245,158,11,0.1)' },
                        err:      { DEFAULT: '#ef4444', dim: '#991b1b', bg: 'rgba(239,68,68,0.1)' },
                        info:     { DEFAULT: '#6366f1', bg: 'rgba(99,102,241,0.1)' },
                    },
                    fontFamily: {
                        sans: ['"Inter"', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'Consolas', 'monospace'],
                    },
                    fontSize: {
                        '2xs': ['0.625rem', { lineHeight: '0.875rem' }],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', Consolas, monospace; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #27272a; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #3f3f46; }
        ::-webkit-scrollbar-corner { background: transparent; }

        @keyframes pulse-dot { 0%,100% { opacity:1 } 50% { opacity:.4 } }
        .dot-pulse { animation: pulse-dot 1.5s ease-in-out infinite; }

        .row-selected { background: rgba(59,130,246,0.08) !important; }
        tr:hover { background: rgba(255,255,255,0.02); }
    </style>
    @livewireStyles
</head>
<body class="h-full bg-bg text-fg overflow-hidden">
    {{ $slot }}
    @livewireScripts
</body>
</html>
