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
    {{-- Update button --}}
    <button @click="open = !open; if(open && $wire.state === 'idle') { $wire.checkForUpdates() }"
            class="h-9 w-9 rounded-lg bg-app2 border flex items-center justify-center transition-all relative
                   {{ in_array($state, ['available', 'ready']) ? 'border-[var(--c-accent)] text-[var(--c-accent)]' : 'border-line text-tertiary hover:text-secondary hover:border-line2' }}"
            title="Actualizaciones">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        @if($state === 'available' || $state === 'ready')
        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full dot-pulse" style="background: var(--c-accent)"></span>
        @endif
    </button>

    {{-- Dropdown --}}
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Actualizaciones
            </h3>
            <p class="text-2xs text-tertiary mt-0.5">v{{ config('nativephp.version', '1.0.0') }}</p>
        </div>

        {{-- Content --}}
        <div class="p-4">
            @switch($state)
                @case('idle')
                @case('checking')
                    <div class="flex flex-col items-center gap-3 py-2">
                        <div class="w-5 h-5 border-2 border-line border-t-[var(--c-accent)] rounded-full animate-spin"></div>
                        <p class="text-[13px] text-secondary">Buscando actualizaciones...</p>
                    </div>
                    @break

                @case('up-to-date')
                    <div class="flex flex-col items-center gap-2 py-2">
                        <svg class="w-8 h-8" style="color: var(--c-ok)" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <p class="text-[13px] font-medium text-primary">Estás al día</p>
                        <p class="text-2xs text-tertiary">No hay actualizaciones disponibles</p>
                        <button wire:click="checkForUpdates"
                                class="mt-1 text-2xs text-link hover:underline">
                            Comprobar de nuevo
                        </button>
                    </div>
                    @break

                @case('available')
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-panel3 text-tertiary">v{{ config('nativephp.version', '1.0.0') }}</span>
                            <svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-accent-s c-accent">v{{ $newVersion }}</span>
                        </div>
                        @if($releaseNotes)
                        <p class="text-2xs text-secondary leading-relaxed bg-panel2 rounded-lg p-2.5 max-h-24 overflow-y-auto">{{ $releaseNotes }}</p>
                        @endif
                        <div class="flex gap-2">
                            <button wire:click="downloadUpdate"
                                    class="flex-1 h-8 rounded-lg text-[13px] font-semibold text-white transition-all flex items-center justify-center gap-1.5"
                                    style="background: var(--c-accent)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                </svg>
                                Descargar
                            </button>
                            <button wire:click="dismiss" @click="open = false"
                                    class="h-8 px-3 rounded-lg text-[13px] text-secondary bg-app2 border border-line hover:border-line2 transition-all">
                                Ahora no
                            </button>
                        </div>
                    </div>
                    @break

                @case('downloading')
                    <div class="flex flex-col gap-3 py-1" wire:poll.500ms>
                        <div class="flex items-center justify-between">
                            <p class="text-[13px] text-secondary">Descargando...</p>
                            <span class="text-[13px] font-mono font-medium text-primary tabular-nums">{{ $downloadPercent }}%</span>
                        </div>
                        <div class="w-full h-1.5 bg-app3 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300" style="width: {{ $downloadPercent }}%; background: var(--c-accent)"></div>
                        </div>
                    </div>
                    @break

                @case('ready')
                    <div class="flex flex-col items-center gap-3 py-2">
                        <svg class="w-8 h-8 c-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <p class="text-[13px] font-medium text-primary">Listo para instalar</p>
                        <p class="text-2xs text-tertiary text-center">La app se reiniciará para aplicar la actualización</p>
                        <div class="flex gap-2 w-full mt-1">
                            <button wire:click="installUpdate"
                                    class="flex-1 h-8 rounded-lg text-[13px] font-semibold text-white transition-all flex items-center justify-center gap-1.5"
                                    style="background: var(--c-accent)">
                                Reiniciar y actualizar
                            </button>
                            <button wire:click="dismiss" @click="open = false"
                                    class="h-8 px-3 rounded-lg text-[13px] text-secondary bg-app2 border border-line hover:border-line2 transition-all">
                                Luego
                            </button>
                        </div>
                    </div>
                    @break

                @case('error')
                    <div class="flex flex-col items-center gap-2 py-2">
                        <svg class="w-8 h-8 c-err" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                        <p class="text-[13px] font-medium text-primary">Error al buscar actualizaciones</p>
                        @if($errorMessage)
                        <p class="text-2xs text-tertiary text-center">{{ $errorMessage }}</p>
                        @endif
                        <button wire:click="checkForUpdates"
                                class="mt-1 text-2xs text-link hover:underline">
                            Reintentar
                        </button>
                    </div>
                    @break
            @endswitch
        </div>
    </div>
</div>
