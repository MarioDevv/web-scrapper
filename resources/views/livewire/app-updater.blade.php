<div class="relative" x-data="{ open: false }"
     x-init="
        if (window.ipcRenderer || window.electron) {
            const ipc = window.ipcRenderer || window.electron?.ipcRenderer;
            if (ipc && ipc.on) {
                ipc.on('native-event', (event, data) => {
                    const eventName = data.event || '';
                    const payload = data.payload || {};

                    if (eventName.includes('UpdateAvailable')) {
                        $wire.dispatch('updater-update-available', { version: payload.version || '', releaseNotes: payload.releaseNotes || '' });
                    } else if (eventName.includes('UpdateNotAvailable')) {
                        $wire.dispatch('updater-update-not-available');
                    } else if (eventName.includes('DownloadProgress')) {
                        $wire.dispatch('updater-download-progress', { percent: Math.round(payload.percent || 0) });
                    } else if (eventName.includes('UpdateDownloaded')) {
                        $wire.dispatch('updater-update-downloaded');
                    } else if (eventName.includes('Error') && eventName.includes('AutoUpdater')) {
                        $wire.dispatch('updater-error', { message: payload.message || '' });
                    }
                });
            }
        }
     ">

    {{-- ── TRIGGER BUTTON ─────────────────────────────────────── --}}
    @php $hasUpdate = in_array($state, ['available', 'ready']); @endphp
    <button @click="open = !open; if(open && $wire.state === 'idle') { $wire.checkForUpdates() }"
            class="h-9 w-9 bg-app2 border flex items-center justify-center transition-all duration-100 active:scale-[0.95] relative font-mono text-[13px] leading-none
                   {{ $hasUpdate
                        ? 'border-line3 c-accent'
                        : 'border-line text-tertiary hover:text-secondary hover:border-line2' }}"
            title="Updates">
        <span>↓</span>
        @if($hasUpdate)
            <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full dot-pulse"
                  style="background: var(--c-accent); box-shadow: 0 0 4px var(--c-accent-glow);"></span>
        @endif
    </button>

    {{-- ── DROPDOWN ───────────────────────────────────────────── --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         @click.outside="open = false"
         class="absolute right-0 top-full mt-1 w-80 bg-panel border border-line2 shadow-2xl z-50 overflow-hidden app-no-drag">

        {{-- Header --}}
        <div class="px-3 py-2 border-b border-line bg-panel2 flex items-center justify-between font-mono text-[10px] uppercase tracking-[0.16em]">
            <span class="c-accent">/etc/updater.conf</span>
            <span class="text-muted">v{{ config('nativephp.version', '1.0.0') }}</span>
        </div>

        {{-- Content by state --}}
        <div class="p-4 font-mono">
            @switch($state)
                @case('idle')
                @case('checking')
                    <div class="flex items-center gap-2.5 py-1">
                        <span class="c-accent text-[14px] animate-spin inline-block leading-none">↻</span>
                        <span class="text-[12px] text-secondary ellipsis">checking for updates</span>
                    </div>
                    @break

                @case('up-to-date')
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2 text-[12px] c-ok">
                            <span>✓</span>
                            <span>you are up to date</span>
                        </div>
                        <div class="text-[10px] text-muted uppercase tracking-[0.14em]">no updates available</div>
                        <button wire:click="checkForUpdates"
                                class="self-start mt-1 text-[10px] uppercase tracking-[0.14em] text-tertiary hover:c-accent transition-all duration-100 active:scale-[0.97] flex items-center gap-1">
                            <span>↻</span>
                            <span>check again</span>
                        </button>
                    </div>
                    @break

                @case('available')
                    <div class="flex flex-col gap-3">
                        {{-- Version line --}}
                        <div class="flex items-center gap-2 text-[11px]">
                            <span class="text-muted">current</span>
                            <span class="text-tertiary">v{{ config('nativephp.version', '1.0.0') }}</span>
                            <span class="c-accent">→</span>
                            <span class="c-accent font-semibold">v{{ $newVersion }}</span>
                            <span class="badge badge-ok ml-auto">new</span>
                        </div>

                        @if($releaseNotes)
                        <div class="bg-app2 border border-line p-2.5 max-h-24 overflow-y-auto">
                            <div class="text-[9px] uppercase tracking-[0.16em] text-muted mb-1">release_notes</div>
                            <pre class="text-[11px] text-secondary leading-relaxed whitespace-pre-wrap font-mono">{{ $releaseNotes }}</pre>
                        </div>
                        @endif

                        <div class="flex gap-2">
                            <button wire:click="downloadUpdate"
                                    class="flex-1 h-8 text-[10px] uppercase tracking-[0.14em] font-semibold flex items-center justify-center gap-1.5 transition-all duration-100 hover:brightness-110 active:scale-[0.97] active:brightness-90"
                                    style="background: var(--c-accent); color: #0a0c0a; box-shadow: 0 0 12px var(--c-accent-glow);">
                                <span>↓</span>
                                <span>download</span>
                            </button>
                            <button wire:click="dismiss" @click="open = false"
                                    class="h-8 px-3 text-[10px] uppercase tracking-[0.14em] text-tertiary bg-app2 border border-line hover:border-line2 hover:text-secondary transition-all duration-100 active:scale-[0.97]">
                                later
                            </button>
                        </div>
                    </div>
                    @break

                @case('downloading')
                    <div class="flex flex-col gap-2.5" wire:poll.500ms>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] c-accent ellipsis">downloading</span>
                            <span class="text-[14px] text-primary tabular-nums leading-none">
                                {{ $downloadPercent }}<span class="text-muted text-[11px]">%</span>
                            </span>
                        </div>
                        <div class="h-[6px] progress-track">
                            <div class="progress-fill is-active"
                                 style="width: {{ $downloadPercent }}%;
                                        background: var(--c-accent);
                                        box-shadow: 0 0 8px var(--c-accent-glow);"></div>
                        </div>
                    </div>
                    @break

                @case('ready')
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-2 text-[12px] c-accent">
                            <span>✓</span>
                            <span>update ready to install</span>
                        </div>
                        <div class="text-[10px] text-muted uppercase tracking-[0.14em]">
                            app will restart to apply the update
                        </div>
                        <div class="flex gap-2 w-full mt-1">
                            <button wire:click="installUpdate"
                                    class="flex-1 h-8 text-[10px] uppercase tracking-[0.14em] font-semibold flex items-center justify-center gap-1.5 transition-all duration-100 hover:brightness-110 active:scale-[0.97] active:brightness-90"
                                    style="background: var(--c-accent); color: #0a0c0a; box-shadow: 0 0 12px var(--c-accent-glow);">
                                <span>▶</span>
                                <span>restart &amp; update</span>
                            </button>
                            <button wire:click="dismiss" @click="open = false"
                                    class="h-8 px-3 text-[10px] uppercase tracking-[0.14em] text-tertiary bg-app2 border border-line hover:border-line2 hover:text-secondary transition-all duration-100 active:scale-[0.97]">
                                later
                            </button>
                        </div>
                    </div>
                    @break

                @case('error')
                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-[12px] c-err">
                            <span>✗</span>
                            <span>error checking for updates</span>
                        </div>
                        @if($errorMessage)
                        <div class="bg-err-s border p-2 text-[10px] text-secondary leading-relaxed"
                             style="border-color: var(--c-err);">
                            <span class="c-err">└─</span> {{ $errorMessage }}
                        </div>
                        @endif
                        <button wire:click="checkForUpdates"
                                class="self-start mt-1 text-[10px] uppercase tracking-[0.14em] text-tertiary hover:c-accent transition-all duration-100 active:scale-[0.97] flex items-center gap-1">
                            <span>↻</span>
                            <span>retry</span>
                        </button>
                    </div>
                    @break
            @endswitch
        </div>
    </div>
</div>
