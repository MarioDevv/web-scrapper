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

            <button @click="dark = !dark" class="ml-1 p-1 rounded-md hover:bg-panel3 transition-colors text-tertiary hover:text-secondary" title="Cambiar tema">
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
                @if($crawling || $paused) disabled @endif autocomplete="off" spellcheck="false">
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
                <input type="number" wire:model="maxPages" class="w-14 bg-transparent text-[13px] font-mono text-primary text-center tabular-nums" @if($crawling || $paused) disabled @endif>
            </div>
            <div class="flex items-center gap-1.5 bg-app2 border border-line rounded-lg px-2.5 h-9">
                <span class="text-2xs text-tertiary">Depth</span>
                <input type="number" wire:model="maxDepth" class="w-10 bg-transparent text-[13px] font-mono text-primary text-center tabular-nums" @if($crawling || $paused) disabled @endif>
            </div>
        </div>

        {{-- ═══ Advanced Options Dropdown ═══ --}}
        @php $hasAdvanced = $crawlResources || $crawlSubdomains || $followExternalLinks; @endphp
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open"
                    :disabled="@js($crawling || $paused)"
                    class="h-9 w-9 rounded-lg bg-app2 border flex items-center justify-center transition-all relative
                           {{ $hasAdvanced ? 'border-[var(--c-accent)] text-[var(--c-accent)]' : 'border-line text-tertiary hover:text-secondary hover:border-line2' }}
                           {{ ($crawling || $paused) ? 'opacity-50 cursor-not-allowed' : '' }}"
                    :class="{ 'border-[var(--c-accent)] text-[var(--c-accent)]': open }"
                    title="Opciones avanzadas">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                @if($hasAdvanced)
                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full" style="background: var(--c-accent)"></span>
                @endif
            </button>

            {{-- Dropdown Panel --}}
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                 @click.outside="open = false"
                 class="absolute right-0 top-full mt-2 w-80 bg-panel border border-line rounded-xl shadow-2xl z-50 overflow-hidden">

                {{-- Header --}}
                <div class="px-4 py-3 border-b border-line bg-panel2">
                    <h3 class="text-[13px] font-semibold text-primary flex items-center gap-2">
                        <svg class="w-4 h-4 text-tertiary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
                        </svg>
                        Opciones de rastreo
                    </h3>
                    <p class="text-2xs text-tertiary mt-0.5">Configuración avanzada del crawler</p>
                </div>

                {{-- Options --}}
                <div class="p-2 space-y-0.5">

                    {{-- Crawl Resources --}}
                    <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-panel2 cursor-pointer transition-colors">
                        <input type="checkbox" wire:model.live="crawlResources"
                               class="mt-0.5 w-4 h-4 rounded border-line text-[var(--c-accent)] bg-app2 focus:ring-[var(--c-accent)] focus:ring-offset-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-[13px] font-medium text-primary">Recursos estáticos</span>
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-info-s c-info">CSS · JS · IMG</span>
                            </div>
                            <p class="text-2xs text-tertiary mt-1 leading-relaxed">
                                Rastrea archivos CSS, JavaScript, imágenes y otros recursos para detectar errores 404 y analizar tamaños
                            </p>
                        </div>
                    </label>

                    {{-- Crawl Subdomains --}}
                    <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-panel2 cursor-pointer transition-colors">
                        <input type="checkbox" wire:model.live="crawlSubdomains"
                               class="mt-0.5 w-4 h-4 rounded border-line text-[var(--c-accent)] bg-app2 focus:ring-[var(--c-accent)] focus:ring-offset-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-[13px] font-medium text-primary">Subdominios</span>
                            </div>
                            <p class="text-2xs text-tertiary mt-1 leading-relaxed">
                                Incluye subdominios como <code class="font-mono text-secondary bg-panel3 px-1 rounded">blog.ejemplo.com</code> en el rastreo
                            </p>
                        </div>
                    </label>

                    {{-- Follow External Links --}}
                    <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-panel2 cursor-pointer transition-colors">
                        <input type="checkbox" wire:model.live="followExternalLinks"
                               class="mt-0.5 w-4 h-4 rounded border-line text-[var(--c-accent)] bg-app2 focus:ring-[var(--c-accent)] focus:ring-offset-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-[13px] font-medium text-primary">Links externos</span>
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-warn-s c-warn">+ lento</span>
                            </div>
                            <p class="text-2xs text-tertiary mt-1 leading-relaxed">
                                Verifica el estado HTTP de enlaces salientes a otros dominios
                            </p>
                        </div>
                    </label>

                </div>

                {{-- Footer --}}
                <div class="px-4 py-2.5 border-t border-line bg-panel2">
                    <p class="text-2xs text-muted flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                        </svg>
                        Activar opciones aumenta el tiempo de rastreo
                    </p>
                </div>
            </div>
        </div>

        {{-- Updater --}}
        <livewire:app-updater />

        {{-- Actions --}}
        @if($crawling)
            <button wire:click="pauseCrawl"
                class="h-9 px-3 rounded-lg text-[13px] font-medium bg-app2 border border-line text-secondary hover:text-primary hover:border-line2 transition-all flex items-center gap-1.5" title="Pausar">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                Pausar
            </button>
            <button wire:click="cancelCrawl"
                class="h-9 px-3 rounded-lg text-[13px] font-medium bg-err-s c-err border border-line hover:brightness-110 transition-all flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                Stop
            </button>
        @elseif($paused)
            <button wire:click="resumeCrawl"
                class="h-9 px-4 rounded-lg text-[13px] font-semibold text-white transition-all flex items-center gap-1.5"
                style="background:var(--c-accent)">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                Reanudar
            </button>
            <button wire:click="cancelCrawl"
                class="h-9 px-3 rounded-lg text-[13px] font-medium bg-err-s c-err border border-line hover:brightness-110 transition-all flex items-center gap-1.5">
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
                     style="width:{{ $prog['pct'] }}%; background:{{ $crawling ? 'var(--c-accent)' : ($paused ? 'var(--c-warn)' : ($status['status'] === 'completed' ? 'var(--c-ok)' : 'var(--c-err)')) }}"></div>
            </div>
        </div>
        <div class="flex items-center gap-3 text-2xs tabular-nums shrink-0">
            @if($crawling)
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent)"></span>
                    <span class="c-accent font-medium">Rastreando</span>
                </span>
            @elseif($paused)
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c-warn)"></span>
                    <span class="c-warn font-medium">Pausado</span>
                </span>
            @else
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $status['status'] === 'completed' ? 'var(--c-ok)' : 'var(--c-err)' }}"></span>
                    <span class="text-secondary">{{ match($status['status']) { 'completed' => 'Completado', 'cancelled' => 'Cancelado', 'failed' => 'Error', default => ucfirst($status['status']) } }}</span>
                </span>
            @endif

            <span><span class="text-primary font-medium">{{ $prog['label'] }}</span> <span class="text-muted">páginas</span></span>

            @if($prog['rate'] > 0 && $crawling)
                <span class="text-muted">{{ $prog['rate'] }} p/s</span>
            @endif

            {{-- Active options badges --}}
            @if($crawlResources || $crawlSubdomains || $followExternalLinks)
                <span class="flex items-center gap-1">
                    @if($crawlResources)<span class="text-[9px] font-semibold px-1 py-0.5 rounded bg-info-s c-info">RES</span>@endif
                    @if($crawlSubdomains)<span class="text-[9px] font-semibold px-1 py-0.5 rounded bg-info-s c-info">SUB</span>@endif
                    @if($followExternalLinks)<span class="text-[9px] font-semibold px-1 py-0.5 rounded bg-warn-s c-warn">EXT</span>@endif
                </span>
            @endif

            @if($status['errorsFound'] > 0)<span class="badge badge-err">{{ $status['errorsFound'] }} err</span>@endif
            @if($status['warningsFound'] > 0)<span class="badge badge-warn">{{ $status['warningsFound'] }} warn</span>@endif
            @if($status['duration'])<span class="text-muted">{{ number_format($status['duration'], 1) }}s</span>@endif
        </div>
    </div>
    @endif
</header>
