{{-- Single audit item in sidebar — Terminal Operator style --}}
@php
    $isSelected  = $auditId === $audit['id'];
    $statusGlyph = match($audit['status']) {
        'completed' => '●',
        'running'   => '●',
        'failed'    => '✗',
        default     => '○',
    };
    $statusColor = match($audit['status']) {
        'completed' => 'var(--c-ok)',
        'running'   => 'var(--c-accent)',
        'failed'    => 'var(--c-err)',
        default     => 'var(--c-fg4)',
    };
    $isRunning = $audit['status'] === 'running';
@endphp
<div class="group/audit relative"
     x-data="{ showMenu: false }"
     @click.away="showMenu = false">

    <div class="flex items-stretch gap-0.5">
        {{-- Main button --}}
        <button wire:click="loadAudit('{{ $audit['id'] }}')"
                class="flex-1 text-left px-2 py-1.5 min-w-0 transition-colors border
                       {{ $isSelected
                            ? 'bg-accent-s border-line2'
                            : 'hover:bg-panel2 border-transparent' }}">
            <div class="flex items-center gap-1.5">
                {{-- Selection indicator OR status glyph --}}
                @if($isSelected)
                    <span class="c-accent font-mono text-[11px] shrink-0 leading-none"
                          style="text-shadow: 0 0 4px var(--c-accent-glow);">▸</span>
                @else
                    <span class="font-mono text-[11px] shrink-0 leading-none {{ $isRunning ? 'dot-pulse' : '' }}"
                          style="color: {{ $statusColor }}; {{ $isRunning ? 'text-shadow: 0 0 4px var(--c-accent-glow);' : '' }}">
                        {{ $statusGlyph }}
                    </span>
                @endif

                <span class="text-[11px] font-mono truncate {{ $isSelected ? 'text-primary' : 'text-secondary' }}">
                    {{ parse_url($audit['seed_url'], PHP_URL_HOST) }}
                </span>
            </div>

            <div class="flex items-center gap-2 mt-0.5 pl-4 text-[10px] font-mono text-muted tabular-nums leading-none">
                <span>{{ $audit['pages_crawled'] }}<span class="text-tertiary">p</span></span>
                @if($audit['errors_found'] > 0)
                    <span class="c-err">·{{ $audit['errors_found'] }}<span class="opacity-60">e</span></span>
                @endif
                @if($audit['warnings_found'] > 0)
                    <span class="c-warn">·{{ $audit['warnings_found'] }}<span class="opacity-60">w</span></span>
                @endif
                <span class="ml-auto text-tertiary">{{ \Carbon\Carbon::parse($audit['created_at'])->format('M-d·H:i') }}</span>
            </div>
        </button>

        {{-- Context menu trigger --}}
        <button @click.stop="showMenu = !showMenu"
                class="opacity-0 group-hover/audit:opacity-100 w-5 flex items-center justify-center font-mono text-[13px] text-muted hover:c-accent hover:bg-panel3 transition-all shrink-0 leading-none">
            ⋮
        </button>
    </div>

    {{-- Context menu --}}
    <div x-show="showMenu" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 -translate-y-0.5"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute right-0 top-full z-30 mt-1 w-48 bg-panel border border-line2 shadow-2xl overflow-hidden font-mono"
         style="display:none">

        {{-- Move to folder --}}
        @if(count($folders) > 0)
        <div class="px-2.5 py-1.5 border-b border-line bg-panel2 flex items-center justify-between">
            <span class="text-[9px] uppercase tracking-[0.16em] c-accent">mv →</span>
            <span class="text-[9px] text-muted">folder</span>
        </div>
        @foreach($folders as $folder)
            @if(($audit['folder_id'] ?? null) !== $folder['id'])
            <button wire:click="moveAuditToFolder('{{ $audit['id'] }}', '{{ $folder['id'] }}')" @click="showMenu = false"
                    class="w-full text-left px-2.5 py-1.5 text-[11px] text-secondary hover:bg-panel2 hover:c-accent flex items-center gap-2 transition-colors">
                <span class="shrink-0 text-[10px]" style="color: {{ $folder['color'] }};">■</span>
                <span class="truncate">{{ $folder['name'] }}</span>
            </button>
            @endif
        @endforeach
        @if($audit['folder_id'])
        <button wire:click="moveAuditToFolder('{{ $audit['id'] }}', '')" @click="showMenu = false"
                class="w-full text-left px-2.5 py-1.5 text-[11px] text-secondary hover:bg-panel2 hover:c-accent flex items-center gap-2 transition-colors">
            <span class="text-muted shrink-0">←</span>
            <span>unfiled</span>
        </button>
        @endif
        @endif

        {{-- Delete --}}
        <div class="{{ count($folders) > 0 ? 'border-t border-line' : '' }}">
            <button wire:click="deleteAudit('{{ $audit['id'] }}')"
                    wire:confirm="Delete this audit? This cannot be undone."
                    @click="showMenu = false"
                    class="w-full text-left px-2.5 py-1.5 text-[11px] c-err hover:bg-err-s flex items-center gap-2 transition-colors">
                <span class="shrink-0">✗</span>
                <span>rm audit</span>
            </button>
        </div>
    </div>
</div>
