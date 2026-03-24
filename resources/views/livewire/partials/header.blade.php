{{-- ══ HEADER ══ --}}
<header class="flex-none bg-panel border-b border-line px-3 py-2.5">
    <div class="flex items-center gap-2.5">

        {{-- Logo + Theme toggle --}}
        <div class="flex items-center gap-2 pr-3 border-r border-line">
            <div class="w-7 h-7 rounded-lg bg-accent-s flex items-center justify-center">
                <svg class="w-3.5 h-3.5 c-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <circle cx="11" cy="11" r="6"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <span class="text-2xs font-semibold text-tertiary tracking-widest hidden xl:block">SEO SPIDER</span>

            {{-- Light/Dark toggle --}}
            <button @click="dark = !dark" class="ml-1 p-1 rounded-md hover:bg-panel3 transition-colors text-tertiary hover:text-secondary" title="Toggle theme">
                <template x-if="dark">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                </template>
                <template x-if="!dark">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </template>
            </button>
        </div>

        {{-- URL Input --}}
        <div class="flex-1 relative group">
            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-muted group-focus-within:text-link transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M13.828 10.172a4 4 0 0 0-5.656 0l-4 4a4 4 0 1 0 5.656 5.656l1.102-1.101"/>
                    <path stroke-linecap="round" d="M10.172 13.828a4 4 0 0 0 5.656 0l4-4a4 4 0 0 0-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <input type="url" wire:model="url" wire:keydown.enter="startCrawl"
                placeholder="https://example.com"
                class="w-full h-9 bg-app2 border border-line rounded-lg pl-9 pr-10 text-[13px] font-mono text-primary placeholder:text-muted
                       focus:border-[var(--c-accent)] focus:ring-1 focus:ring-[var(--c-accent-bg)] transition-all duration-200"
                @if($crawling) disabled @endif autocomplete="off" spellcheck="false">
            @if($crawling)
            <div class="absolute right-3 top-1/2 -translate-y-1/2">
                <div class="w-4 h-4 border-2 border-line border-t-[var(--c-accent)] rounded-full animate-spin"></div>
            </div>
            @endif
        </div>

        {{-- Config --}}
        <div class="flex items-center gap-1.5">
            <div class="flex items-center gap-1.5 bg-app2 border border-line rounded-lg px-2.5 h-9">
                <span class="text-2xs text-tertiary">Max</span>
                <input type="number" wire:model="maxPages" class="w-14 bg-transparent text-[13px] font-mono text-primary text-center tabular-nums" @if($crawling) disabled @endif>
            </div>
            <div class="flex items-center gap-1.5 bg-app2 border border-line rounded-lg px-2.5 h-9">
                <span class="text-2xs text-tertiary">Depth</span>
                <input type="number" wire:model="maxDepth" class="w-10 bg-transparent text-[13px] font-mono text-primary text-center tabular-nums" @if($crawling) disabled @endif>
            </div>
        </div>

        {{-- Action --}}
        @if($crawling)
            <button wire:click="cancelCrawl"
                class="h-9 px-4 rounded-lg text-[13px] font-medium bg-err-s c-err border border-line hover:brightness-110 transition-all flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                Stop
            </button>
        @else
            <button wire:click="startCrawl"
                class="h-9 px-5 rounded-lg text-[13px] font-semibold text-white transition-all flex items-center gap-1.5"
                style="background:var(--c-accent)">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 12h14m-7-7 7 7-7 7"/></svg>
                Crawl
            </button>
        @endif
    </div>

    @error('url') <p class="c-err text-2xs mt-1.5 pl-12">{{ $message }}</p> @enderror

    {{-- Progress Bar --}}
    @if($status)
    @php $prog = $this->progress; @endphp
    <div class="mt-2.5 flex items-center gap-3">
        <div class="flex-1">
            <div class="w-full h-1 bg-app3 rounded-full overflow-hidden">
                <div class="progress-fill h-full rounded-full"
                     style="width:{{ $prog['pct'] }}%; background:{{ $crawling ? 'var(--c-accent)' : ($status['status'] === 'completed' ? 'var(--c-ok)' : 'var(--c-err)') }}"></div>
            </div>
        </div>
        <div class="flex items-center gap-3 text-2xs tabular-nums shrink-0">
            @if($crawling)
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent)"></span>
                    <span class="c-accent font-medium">Crawling</span>
                </span>
            @else
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $status['status'] === 'completed' ? 'var(--c-ok)' : 'var(--c-err)' }}"></span>
                    <span class="text-secondary">{{ ucfirst($status['status']) }}</span>
                </span>
            @endif

            <span><span class="text-primary font-medium">{{ $prog['label'] }}</span> <span class="text-muted">pages</span></span>

            @if($prog['rate'] > 0 && $crawling)
                <span class="text-muted">{{ $prog['rate'] }} p/s</span>
            @endif

            @if($status['errorsFound'] > 0)<span class="badge badge-err">{{ $status['errorsFound'] }} err</span>@endif
            @if($status['warningsFound'] > 0)<span class="badge badge-warn">{{ $status['warningsFound'] }} warn</span>@endif
            @if($status['duration'])<span class="text-muted">{{ number_format($status['duration'], 1) }}s</span>@endif
        </div>
    </div>
    @endif
</header>
