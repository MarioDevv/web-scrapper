{{-- ══ SIDEBAR ══ --}}
<aside class="flex-none {{ $sidebarCollapsed ? 'w-11' : 'w-60' }} bg-panel border-r border-line flex flex-col min-h-0 transition-all duration-200">

    {{-- Toggle --}}
    <button wire:click="toggleSidebar" class="flex-none h-9 flex items-center justify-center border-b border-line text-muted hover:text-secondary transition-colors">
        <svg class="w-3.5 h-3.5 transition-transform duration-200 {{ $sidebarCollapsed ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
        </svg>
    </button>

    @if(!$sidebarCollapsed)

    {{-- Stats (when audit loaded) --}}
    @if($status)
    <div class="flex-none p-3 border-b border-line">
        <div class="grid grid-cols-2 gap-1.5">
            @php
                $stats = [
                    ['Crawled',  $status['pagesCrawled'],    null],
                    ['Failed',   $status['pagesFailed'],     $status['pagesFailed'] > 0 ? 'var(--c-err)' : null],
                    ['Errors',   $status['errorsFound'],     $status['errorsFound'] > 0 ? 'var(--c-err)' : null],
                    ['Warnings', $status['warningsFound'],   $status['warningsFound'] > 0 ? 'var(--c-warn)' : null],
                ];
            @endphp
            @foreach($stats as [$label, $value, $color])
            <div class="stat-card bg-app2 rounded-lg px-2.5 py-2 border border-line">
                <div class="text-2xs text-tertiary mb-0.5">{{ $label }}</div>
                <div class="text-[15px] font-bold tabular-nums leading-none" style="{{ $color ? "color:{$color}" : '' }}">{{ $value }}</div>
            </div>
            @endforeach
        </div>
        <div class="flex gap-4 text-2xs text-tertiary mt-2">
            <span>Discovered: <span class="text-secondary font-medium tabular-nums">{{ $status['pagesDiscovered'] }}</span></span>
            @if($status['duration'])<span>{{ number_format($status['duration'], 1) }}s</span>@endif
        </div>
    </div>
    @endif

    {{-- History with folders --}}
    <div class="flex-1 overflow-y-auto" x-data="{ openFolders: JSON.parse(localStorage.getItem('sf') || '{}'), movingAudit: null }"
         x-effect="localStorage.setItem('sf', JSON.stringify(openFolders))">

        {{-- Header --}}
        <div class="px-3 pt-3 pb-1 flex items-center justify-between">
            <h3 class="text-2xs uppercase tracking-widest text-muted font-semibold">History</h3>
            <button @click="$wire.showNewFolder = !$wire.showNewFolder" class="text-muted hover:text-secondary transition-colors p-0.5 rounded hover:bg-panel2" title="New folder">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.06-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.38a1.5 1.5 0 01-1.06-.44z"/>
                </svg>
            </button>
        </div>

        {{-- New folder input --}}
        @if($showNewFolder)
            <div class="px-2 pb-2">
                <div class="flex items-center gap-1">
                    <input type="text" wire:model="newFolderName" wire:keydown.enter="createFolder" wire:keydown.escape="$set('showNewFolder', false)"
                        placeholder="Folder name…" autofocus
                        class="flex-1 h-7 bg-app2 border border-line rounded-md px-2 text-2xs text-primary placeholder:text-muted focus:border-[var(--c-accent)]">
                    <button wire:click="createFolder" class="h-7 px-2 rounded-md bg-accent-s c-accent text-2xs font-medium hover:brightness-110">Create</button>
                </div>
            </div>
        @endif

        <div class="px-2 pb-3 space-y-1">

            {{-- Folders --}}
            @foreach($folders as $folder)
            @php
                $folderAudits = array_values(array_filter($auditHistory, fn($a) => ($a['folder_id'] ?? null) === $folder['id']));
            @endphp
            <div class="group">
                {{-- Folder header --}}
                <div class="flex items-center gap-1 px-1.5 py-1 rounded-md hover:bg-panel2 transition-colors">
                    <button @click="openFolders['{{ $folder['id'] }}'] = !openFolders['{{ $folder['id'] }}']" class="flex items-center gap-1.5 flex-1 min-w-0">
                        <svg class="w-3 h-3 text-muted transition-transform shrink-0" :class="openFolders['{{ $folder['id'] }}'] ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                        <svg class="w-3.5 h-3.5 shrink-0" style="color:{{ $folder['color'] }}" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>

                        @if($editingFolderId === $folder['id'])
                            <input type="text" wire:model="editingFolderName" wire:keydown.enter="saveFolder" wire:keydown.escape="cancelEditFolder"
                                @click.stop autofocus
                                class="flex-1 h-5 bg-app2 border border-line rounded px-1 text-2xs text-primary focus:border-[var(--c-accent)]">
                        @else
                            <span class="text-2xs font-medium text-secondary truncate">{{ $folder['name'] }}</span>
                        @endif
                    </button>

                    <span class="text-[10px] text-muted tabular-nums mr-1">{{ count($folderAudits) }}</span>

                    {{-- Folder actions --}}
                    <div class="hidden group-hover:flex items-center gap-0.5">
                        @if($editingFolderId === $folder['id'])
                            <button wire:click="saveFolder" class="p-0.5 rounded c-ok hover:bg-panel3" title="Save">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        @else
                            <button wire:click="startEditFolder('{{ $folder['id'] }}')" class="p-0.5 rounded text-muted hover:text-secondary hover:bg-panel3" title="Rename">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                            </button>
                            <button wire:click="deleteFolder('{{ $folder['id'] }}')" wire:confirm="Delete folder '{{ $folder['name'] }}'? Audits will be moved to Unfiled."
                                class="p-0.5 rounded text-muted hover:c-err hover:bg-panel3" title="Delete folder">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Audits in folder --}}
                <div x-show="openFolders['{{ $folder['id'] }}']" x-collapse class="pl-3 mt-0.5 space-y-0.5">
                    @forelse($folderAudits as $audit)
                        @include('livewire.partials.sidebar-audit-item', ['audit' => $audit])
                    @empty
                        <p class="text-[10px] text-muted italic pl-5 py-1">Empty</p>
                    @endforelse
                </div>
            </div>
            @endforeach

            {{-- Unfiled audits --}}
            @php
                $unfiled = array_values(array_filter($auditHistory, fn($a) => empty($a['folder_id'])));
            @endphp
            @if(count($unfiled) > 0 || count($folders) === 0)
            <div class="{{ count($folders) > 0 ? 'mt-2 pt-2 border-t border-line' : '' }}">
                @if(count($folders) > 0)
                <div class="px-1.5 pb-1">
                    <span class="text-[10px] uppercase tracking-widest text-muted font-semibold">Unfiled</span>
                </div>
                @endif
                <div class="space-y-0.5">
                    @forelse($unfiled as $audit)
                        @include('livewire.partials.sidebar-audit-item', ['audit' => $audit])
                    @empty
                        <div class="px-3 py-8 text-center">
                            <svg class="w-8 h-8 text-muted mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="m21 21-4.35-4.35"/></svg>
                            <p class="text-2xs text-muted">No audits yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @endif

        </div>
    </div>

    @endif
</aside>
