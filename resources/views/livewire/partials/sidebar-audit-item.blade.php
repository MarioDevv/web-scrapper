{{-- Single audit item in sidebar --}}
<div class="group/audit relative"
     x-data="{ showMenu: false }"
     @click.away="showMenu = false">

    <div class="flex items-center gap-0.5">
        {{-- Main button --}}
        <button wire:click="loadAudit('{{ $audit['id'] }}')"
            class="flex-1 text-left px-2 py-1.5 rounded-lg transition-all duration-100 min-w-0
                {{ $auditId === $audit['id']
                    ? 'bg-accent-s border border-line2'
                    : 'hover:bg-panel2 border border-transparent' }}">
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ match($audit['status']) {
                    'completed' => 'c-ok', 'running' => 'c-accent dot-pulse',
                    'failed' => 'c-err', default => ''
                } }}" style="background:currentColor"></span>
                <span class="text-2xs font-mono truncate {{ $auditId === $audit['id'] ? 'text-primary font-medium' : 'text-secondary' }}">
                    {{ parse_url($audit['seed_url'], PHP_URL_HOST) }}
                </span>
            </div>
            <div class="flex items-center gap-2 mt-0.5 pl-3 text-[10px] text-muted tabular-nums">
                <span>{{ $audit['pages_crawled'] }}p</span>
                @if($audit['errors_found'] > 0)<span class="c-err">{{ $audit['errors_found'] }}E</span>@endif
                @if($audit['warnings_found'] > 0)<span class="c-warn">{{ $audit['warnings_found'] }}W</span>@endif
                <span class="ml-auto">{{ \Carbon\Carbon::parse($audit['created_at'])->format('d M H:i') }}</span>
            </div>
        </button>

        {{-- Context menu trigger --}}
        <button @click.stop="showMenu = !showMenu"
            class="opacity-0 group-hover/audit:opacity-100 p-1 rounded text-muted hover:text-secondary hover:bg-panel3 transition-all shrink-0">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="6" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg>
        </button>
    </div>

    {{-- Context menu --}}
    <div x-show="showMenu" x-transition.opacity.duration.150ms
         class="absolute right-0 top-full z-30 mt-1 w-44 bg-panel border border-line2 rounded-lg shadow-lg overflow-hidden"
         style="display:none">

        {{-- Move to folder --}}
        @if(count($folders) > 0)
        <div class="px-2 py-1.5 border-b border-line">
            <span class="text-[10px] uppercase tracking-wider text-muted font-semibold">Move to</span>
        </div>
        @foreach($folders as $folder)
            @if(($audit['folder_id'] ?? null) !== $folder['id'])
            <button wire:click="moveAuditToFolder('{{ $audit['id'] }}', '{{ $folder['id'] }}')" @click="showMenu = false"
                class="w-full text-left px-3 py-1.5 text-2xs text-secondary hover:bg-panel2 flex items-center gap-2 transition-colors">
                <svg class="w-3 h-3 shrink-0" style="color:{{ $folder['color'] }}" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
                {{ $folder['name'] }}
            </button>
            @endif
        @endforeach
        @if($audit['folder_id'])
        <button wire:click="moveAuditToFolder('{{ $audit['id'] }}', '')" @click="showMenu = false"
            class="w-full text-left px-3 py-1.5 text-2xs text-secondary hover:bg-panel2 flex items-center gap-2 transition-colors">
            <svg class="w-3 h-3 text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
            Unfiled
        </button>
        @endif
        @endif

        {{-- Delete --}}
        <div class="{{ count($folders) > 0 ? 'border-t border-line' : '' }}">
            <button wire:click="deleteAudit('{{ $audit['id'] }}')" wire:confirm="Delete this audit? This cannot be undone." @click="showMenu = false"
                class="w-full text-left px-3 py-1.5 text-2xs c-err hover:bg-err-s flex items-center gap-2 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                Delete audit
            </button>
        </div>
    </div>
</div>
