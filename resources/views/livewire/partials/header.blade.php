{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  HEADER — Terminal Operator                                                 --}}
{{--  Three bands: tty-strip (draggable) · command row · status line (on crawl)  --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<header class="flex-none chrome-nosel">

    {{-- ── TTY STRIP ─────────────────────────────────────────────────── --}}
    {{-- Draggable region for NativePHP (when titleBarHidden is enabled).   --}}
    <div class="app-drag h-7 bg-panel2 border-b border-line flex items-center px-3 gap-3 text-[11px] leading-none">
        <div class="flex items-center gap-1.5">
            <span class="c-accent font-mono text-[13px]" style="text-shadow: 0 0 6px var(--c-accent-glow);">▸</span>
            <span class="c-accent font-semibold tracking-tight">seo-spider</span>
            <span class="text-muted">·</span>
            <span class="text-tertiary font-mono text-[10px]">tty0</span>
        </div>

        <span class="text-muted">─</span>

        <div class="text-tertiary font-mono flex items-center gap-1.5 min-w-0">
            @if($status)
                <span class="text-muted">session:</span>
                <span class="text-secondary truncate max-w-[240px]">{{ parse_url($status['seedUrl'] ?? '', PHP_URL_HOST) ?: '—' }}</span>
            @else
                <span class="text-muted">session: idle</span>
            @endif
        </div>

        <div class="flex-1"></div>

        {{-- global state pill --}}
        @if($crawling)
            <span class="flex items-center gap-1.5 c-accent font-mono">
                <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent); box-shadow:0 0 6px var(--c-accent-glow);"></span>
                <span class="text-[10px] uppercase tracking-[0.14em]">live</span>
            </span>
        @elseif($paused)
            <span class="flex items-center gap-1.5 c-warn font-mono">
                <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c-warn);"></span>
                <span class="text-[10px] uppercase tracking-[0.14em]">paused</span>
            </span>
        @else
            <span class="flex items-center gap-1.5 text-tertiary font-mono">
                <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c-fg4);"></span>
                <span class="text-[10px] uppercase tracking-[0.14em]">ready</span>
            </span>
        @endif
    </div>

    {{-- ── COMMAND ROW ───────────────────────────────────────────────── --}}
    <div class="app-no-drag bg-panel border-b border-line px-3 py-2.5">
        <div class="flex items-center gap-2">

            {{-- URL input with prompt prefix --}}
            <div class="flex-1 relative group">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 pointer-events-none select-none">
                    <span class="c-accent font-mono text-[13px] leading-none" style="text-shadow:0 0 6px var(--c-accent-glow);">❯</span>
                    <span class="text-muted font-mono text-[10px] uppercase tracking-[0.16em]">crawl</span>
                </div>

                <input type="url" wire:model="url" wire:keydown.enter="startCrawl"
                       placeholder="example.com"
                       class="w-full h-9 bg-app2 border border-line pl-[88px] pr-11 text-[13px] font-mono text-primary placeholder:text-muted tabular-nums
                              focus:border-line3 focus:bg-app transition-[border-color,background-color] duration-150"
                       @if($crawling || $paused) disabled @endif
                       autocomplete="off" spellcheck="false">

                @if($crawling)
                    <div class="absolute right-3 top-1/2 -translate-y-1/2">
                        <div class="w-3.5 h-3.5 border border-line2 animate-spin" style="border-top-color: var(--c-accent); border-radius: 1px;"></div>
                    </div>
                @endif
            </div>

            {{-- key=value config pairs --}}
            <label class="flex items-center bg-app2 border border-line h-9 pl-2.5 pr-1.5 gap-1.5 cursor-text focus-within:border-line3 transition-colors">
                <span class="text-muted font-mono text-[10px] uppercase tracking-[0.12em]">max</span>
                <span class="text-muted font-mono">=</span>
                <input type="number" wire:model="maxPages"
                       class="w-14 bg-transparent text-[13px] font-mono text-primary tabular-nums"
                       @if($crawling || $paused) disabled @endif>
            </label>

            <label class="flex items-center bg-app2 border border-line h-9 pl-2.5 pr-1.5 gap-1.5 cursor-text focus-within:border-line3 transition-colors">
                <span class="text-muted font-mono text-[10px] uppercase tracking-[0.12em]">depth</span>
                <span class="text-muted font-mono">=</span>
                <input type="number" wire:model="maxDepth"
                       class="w-10 bg-transparent text-[13px] font-mono text-primary tabular-nums"
                       @if($crawling || $paused) disabled @endif>
            </label>

            {{-- advanced options dropdown --}}
            @php
                $hasAdvanced = $crawlResources || $crawlSubdomains || $followExternalLinks;
                $advCount    = ($crawlResources?1:0) + ($crawlSubdomains?1:0) + ($followExternalLinks?1:0);
            @endphp
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                        :disabled="@js($crawling || $paused)"
                        class="h-9 px-3 bg-app2 border flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-[0.12em] transition-colors
                               {{ $hasAdvanced ? 'border-line3 c-accent' : 'border-line text-tertiary hover:text-secondary hover:border-line2' }}
                               {{ ($crawling || $paused) ? 'opacity-50 cursor-not-allowed' : '' }}"
                        :class="{ 'border-line3 c-accent': open }"
                        title="Advanced options">
                    <span>opts</span>
                    @if($hasAdvanced)
                        <span class="c-accent font-bold">·{{ $advCount }}</span>
                    @endif
                </button>

                {{-- dropdown panel --}}
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     @click.outside="open = false"
                     class="absolute right-0 top-full mt-1 w-[400px] bg-panel border border-line2 shadow-2xl z-50 overflow-hidden app-no-drag">

                    {{-- Header --}}
                    <div class="px-4 py-2.5 border-b border-line bg-panel2 flex items-center justify-between font-mono text-[10px] uppercase tracking-[0.16em]">
                        <span class="c-accent">/etc/crawler.conf</span>
                        <span class="text-muted">{{ ($crawlResources?1:0)+($crawlSubdomains?1:0)+($followExternalLinks?1:0) }} / 3</span>
                    </div>

                    {{-- Options list (divider between rows) --}}
                    <div class="divide-y divide-[color:var(--c-border)]">

                        {{-- --crawl-resources --}}
                        <label class="flex items-start gap-3 px-4 py-3 hover:bg-panel2 cursor-pointer transition-colors">
                            <input type="checkbox" wire:model.live="crawlResources" class="sr-only">
                            <span x-text="$wire.crawlResources ? '[x]' : '[ ]'"
                                  :class="$wire.crawlResources ? 'c-accent' : 'text-muted'"
                                  class="font-mono text-[13px] shrink-0 mt-[1px] leading-none transition-colors"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[12px] font-mono"
                                          :class="$wire.crawlResources ? 'c-accent' : 'text-primary'">--crawl-resources</span>
                                    <span class="badge badge-info">css·js·img</span>
                                </div>
                                <p class="text-[11px] text-tertiary mt-1.5 leading-snug font-mono">
                                    <span class="text-muted">#</span> detect 404s and oversized static assets
                                </p>
                            </div>
                        </label>

                        {{-- --include-subdomains --}}
                        <label class="flex items-start gap-3 px-4 py-3 hover:bg-panel2 cursor-pointer transition-colors">
                            <input type="checkbox" wire:model.live="crawlSubdomains" class="sr-only">
                            <span x-text="$wire.crawlSubdomains ? '[x]' : '[ ]'"
                                  :class="$wire.crawlSubdomains ? 'c-accent' : 'text-muted'"
                                  class="font-mono text-[13px] shrink-0 mt-[1px] leading-none transition-colors"></span>
                            <div class="flex-1 min-w-0">
                                <span class="text-[12px] font-mono"
                                      :class="$wire.crawlSubdomains ? 'c-accent' : 'text-primary'">--include-subdomains</span>
                                <p class="text-[11px] text-tertiary mt-1.5 leading-snug font-mono">
                                    <span class="text-muted">#</span> crawl <span class="text-secondary">blog.example.com</span>, <span class="text-secondary">shop.example.com</span>, …
                                </p>
                            </div>
                        </label>

                        {{-- --verify-external --}}
                        <label class="flex items-start gap-3 px-4 py-3 hover:bg-panel2 cursor-pointer transition-colors">
                            <input type="checkbox" wire:model.live="followExternalLinks" class="sr-only">
                            <span x-text="$wire.followExternalLinks ? '[x]' : '[ ]'"
                                  :class="$wire.followExternalLinks ? 'c-accent' : 'text-muted'"
                                  class="font-mono text-[13px] shrink-0 mt-[1px] leading-none transition-colors"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[12px] font-mono"
                                          :class="$wire.followExternalLinks ? 'c-accent' : 'text-primary'">--verify-external</span>
                                    <span class="badge badge-warn">slow</span>
                                </div>
                                <p class="text-[11px] text-tertiary mt-1.5 leading-snug font-mono">
                                    <span class="text-muted">#</span> HEAD request on outbound links to 3rd-party domains
                                </p>
                            </div>
                        </label>
                    </div>

                    {{-- Footer --}}
                    <div class="px-4 py-2.5 border-t border-line bg-panel2 flex items-center gap-2">
                        <span class="c-accent font-mono text-[11px]" style="text-shadow: 0 0 4px var(--c-accent-glow);">▲</span>
                        <span class="font-mono text-[10px] text-muted uppercase tracking-[0.14em]">each flag increases crawl time</span>
                    </div>
                </div>
            </div>

            {{-- updater slot (kept) --}}
            <livewire:app-updater />

            <span class="h-5 w-px" style="background: var(--c-border);"></span>

            {{-- actions --}}
            @if($crawling)
                <button wire:click="pauseCrawl"
                        class="h-9 px-3 text-[11px] font-mono uppercase tracking-[0.14em] bg-app2 border border-line text-secondary hover:text-primary hover:border-line2 transition-colors flex items-center gap-1.5"
                        title="Pause">
                    <span class="c-warn text-[10px]">❚❚</span>
                    <span>pause</span>
                </button>
                <button wire:click="cancelCrawl"
                        class="h-9 px-3 text-[11px] font-mono uppercase tracking-[0.14em] bg-app2 border c-err hover:bg-err-s transition-colors flex items-center gap-1.5"
                        style="border-color: var(--c-err);">
                    <span>■</span>
                    <span>stop</span>
                </button>

            @elseif($paused)
                <button wire:click="resumeCrawl"
                        class="h-9 px-4 text-[11px] font-mono uppercase tracking-[0.14em] font-semibold flex items-center gap-1.5 transition-[filter,box-shadow] hover:brightness-110"
                        style="background: var(--c-accent); color: #0a0c0a; box-shadow: 0 0 16px var(--c-accent-glow);">
                    <span>▶</span>
                    <span>resume</span>
                </button>
                <button wire:click="cancelCrawl"
                        class="h-9 px-3 text-[11px] font-mono uppercase tracking-[0.14em] bg-app2 border c-err hover:bg-err-s transition-colors flex items-center gap-1.5"
                        style="border-color: var(--c-err);">
                    <span>■</span>
                    <span>stop</span>
                </button>

            @else
                <button wire:click="startCrawl"
                        wire:loading.attr="disabled"
                        wire:target="startCrawl"
                        class="group h-9 px-5 text-[11px] font-mono uppercase tracking-[0.14em] font-semibold flex items-center gap-2 transition-[filter,box-shadow,opacity] hover:brightness-110 disabled:opacity-60"
                        style="background: var(--c-accent); color: #0a0c0a; box-shadow: 0 0 16px var(--c-accent-glow);">
                    <span class="transition-transform group-hover:translate-x-0.5">▶</span>
                    <span wire:loading.remove wire:target="startCrawl">exec</span>
                    <span wire:loading wire:target="startCrawl" class="ellipsis">booting</span>
                </button>
            @endif
        </div>

        @error('url')
        <div class="mt-2 pl-[88px] flex items-center gap-2 text-2xs font-mono c-err anim-fade">
            <span class="font-semibold">✗ err</span>
            <span class="text-muted">·</span>
            <span>{{ $message }}</span>
        </div>
        @enderror
    </div>

    {{-- ── STATUS LINE ───────────────────────────────────────────────── --}}
    @if($status)
    @php $prog = $this->progress; @endphp
    <div class="app-no-drag bg-panel2 border-b border-line px-3 py-2 flex items-center gap-3 text-[11px] font-mono leading-none">

        {{-- progress track + percent --}}
        <div class="flex-1 flex items-center gap-2.5 min-w-0">
            <div class="flex-1 h-[6px] progress-track">
                @php
                    $barColor = $crawling ? 'var(--c-accent)'
                              : ($paused ? 'var(--c-warn)'
                              : ($status['status'] === 'completed' ? 'var(--c-ok)' : 'var(--c-err)'));
                @endphp
                <div class="progress-fill {{ $crawling ? 'is-active' : '' }}"
                     style="width: {{ $prog['pct'] }}%;
                            background: {{ $barColor }};
                            {{ $crawling ? 'box-shadow: 0 0 10px '.($barColor).';' : '' }}"></div>
            </div>
            <span class="text-tertiary tabular-nums min-w-[3ch] text-right">{{ number_format($prog['pct'], 0) }}%</span>
        </div>

        {{-- state + counters (pipe-separated, tmux-status style) --}}
        <div class="flex items-center gap-3 tabular-nums shrink-0">
            @if($crawling)
                <span class="flex items-center gap-1.5 c-accent">
                    <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent); box-shadow: 0 0 6px var(--c-accent-glow);"></span>
                    <span class="font-medium ellipsis">crawling</span>
                </span>
            @elseif($paused)
                <span class="flex items-center gap-1.5 c-warn">
                    <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c-warn);"></span>
                    <span class="font-medium">paused</span>
                </span>
            @else
                @php
                    $isDone = $status['status'] === 'completed';
                    $stateColor = $isDone ? 'var(--c-ok)' : 'var(--c-err)';
                    $stateLabel = match($status['status']) {
                        'completed' => 'done',
                        'cancelled' => 'stop',
                        'failed'    => 'fail',
                        default     => strtolower($status['status']),
                    };
                @endphp
                <span class="flex items-center gap-1.5" style="color: {{ $stateColor }};">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $stateColor }};"></span>
                    <span class="font-medium">{{ $stateLabel }}</span>
                </span>
            @endif

            <span class="text-muted">│</span>
            <span>
                <span class="text-primary font-medium">{{ $prog['label'] }}</span>
                <span class="text-muted">pages</span>
            </span>

            @if($prog['rate'] > 0 && $crawling)
                <span class="text-muted">│</span>
                <span>
                    <span class="text-primary font-medium">{{ $prog['rate'] }}</span><span class="text-muted">/s</span>
                </span>
            @endif

            @if($crawlResources || $crawlSubdomains || $followExternalLinks)
                <span class="text-muted">│</span>
                <span class="flex items-center gap-1">
                    @if($crawlResources)     <span class="badge badge-info">res</span> @endif
                    @if($crawlSubdomains)    <span class="badge badge-info">sub</span> @endif
                    @if($followExternalLinks)<span class="badge badge-warn">ext</span> @endif
                </span>
            @endif

            @if($status['errorsFound'] > 0)
                <span class="text-muted">│</span>
                <span class="c-err"><span class="font-medium">{{ $status['errorsFound'] }}</span> err</span>
            @endif
            @if($status['warningsFound'] > 0)
                <span class="c-warn"><span class="font-medium">{{ $status['warningsFound'] }}</span> warn</span>
            @endif

            @if($status['duration'])
                <span class="text-muted">│</span>
                <span class="text-tertiary tabular-nums">{{ number_format($status['duration'], 1) }}s</span>
            @endif
        </div>
    </div>
    @endif
</header>
