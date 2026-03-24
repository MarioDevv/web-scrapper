{{-- ══ SIDEBAR ══ --}}
<aside class="flex-none {{ $sidebarCollapsed ? 'w-11' : 'w-60' }} bg-panel border-r border-line flex flex-col min-h-0 transition-all duration-200">

    {{-- Toggle --}}
    <button wire:click="toggleSidebar" class="flex-none h-9 flex items-center justify-center border-b border-line text-muted hover:text-secondary transition-colors">
        <svg class="w-3.5 h-3.5 transition-transform duration-200 {{ $sidebarCollapsed ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
        </svg>
    </button>

    @if(!$sidebarCollapsed)

    {{-- Score + Stats --}}
    @if($status)
    <div class="flex-none p-3 border-b border-line space-y-3">

        {{-- Audit Score --}}
        @php $score = $this->auditScore; @endphp
        @if($score !== null && !$crawling)
        <div class="flex items-center gap-3">
            <div class="relative w-12 h-12 shrink-0">
                <svg class="w-12 h-12 -rotate-90" viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="15" fill="none" stroke="var(--c-bg3)" stroke-width="2.5"/>
                    <circle cx="18" cy="18" r="15" fill="none" stroke-width="2.5"
                        stroke-dasharray="{{ $score * 0.942 }} 100" stroke-linecap="round"
                        style="stroke:{{ $score >= 80 ? 'var(--c-ok)' : ($score >= 50 ? 'var(--c-warn)' : 'var(--c-err)') }}"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[11px] font-bold"
                      style="color:{{ $score >= 80 ? 'var(--c-ok)' : ($score >= 50 ? 'var(--c-warn)' : 'var(--c-err)') }}">{{ $score }}</span>
            </div>
            <div>
                <div class="text-[13px] font-semibold text-primary">Health Score</div>
                <div class="text-2xs text-tertiary">{{ $score >= 80 ? 'Good' : ($score >= 50 ? 'Needs work' : 'Critical') }}</div>
            </div>
        </div>
        @endif

        {{-- Stats grid --}}
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

        <div class="flex gap-4 text-2xs text-tertiary">
            <span>Discovered: <span class="text-secondary font-medium tabular-nums">{{ $status['pagesDiscovered'] }}</span></span>
            @if($status['duration'])<span>{{ number_format($status['duration'], 1) }}s</span>@endif
        </div>
    </div>
    @endif

    {{-- History grouped by domain --}}
    <div class="flex-1 overflow-y-auto">
        <div class="px-3 pt-3 pb-1.5 flex items-center justify-between">
            <h3 class="text-2xs uppercase tracking-widest text-muted font-semibold">History</h3>
            <span class="text-2xs text-muted">{{ count($auditHistory) }}</span>
        </div>

        <div class="px-2 pb-3 space-y-2"
             x-data="{ openFolders: JSON.parse(localStorage.getItem('openFolders') || '{}') }"
             x-effect="localStorage.setItem('openFolders', JSON.stringify(openFolders))">

            @forelse($this->groupedHistory as $domain => $audits)
            <div>
                {{-- Domain folder header --}}
                <button class="w-full flex items-center gap-1.5 px-2 py-1.5 rounded-md text-2xs text-secondary hover:bg-panel2 transition-colors"
                    @click="openFolders['{{ $domain }}'] = !openFolders['{{ $domain }}']">
                    <svg class="w-3 h-3 text-muted transition-transform"
                         :class="openFolders['{{ $domain }}'] ? 'rotate-90' : ''"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M9 5l7 7-7 7"/>
                    </svg>
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 015.25 3.75h3.69a1.5 1.5 0 011.06.44l1.122 1.122a1.5 1.5 0 001.06.438H18.75A2.25 2.25 0 0121 8.25v1.526"/>
                    </svg>
                    <span class="font-medium truncate">{{ $domain }}</span>
                    <span class="ml-auto text-muted text-[10px]">{{ count($audits) }}</span>
                </button>

                {{-- Audit items in this domain --}}
                <div x-show="openFolders['{{ $domain }}']" x-collapse class="pl-4 mt-0.5 space-y-0.5">
                    @foreach($audits as $audit)
                    <button wire:click="loadAudit('{{ $audit['id'] }}')"
                        class="w-full text-left px-2 py-1.5 rounded-md transition-all duration-100
                            {{ $auditId === $audit['id']
                                ? 'bg-accent-s border border-line2'
                                : 'hover:bg-panel2 border border-transparent' }}">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ match($audit['status']) {
                                'completed' => 'c-ok', 'running' => 'c-accent dot-pulse',
                                'failed' => 'c-err', default => ''
                            } }}" style="background:currentColor"></span>
                            <span class="text-2xs truncate {{ $auditId === $audit['id'] ? 'text-primary font-medium' : 'text-secondary' }}">
                                {{ parse_url($audit['seed_url'], PHP_URL_PATH) ?: '/' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5 pl-3 text-[10px] text-muted tabular-nums">
                            <span>{{ $audit['pages_crawled'] }}p</span>
                            @if($audit['errors_found'] > 0)<span class="c-err">{{ $audit['errors_found'] }}E</span>@endif
                            @if($audit['warnings_found'] > 0)<span class="c-warn">{{ $audit['warnings_found'] }}W</span>@endif
                            <span class="ml-auto">{{ \Carbon\Carbon::parse($audit['created_at'])->format('d M H:i') }}</span>
                        </div>
                    </button>
                    @endforeach
                </div>
            </div>
            @empty
            <div class="px-3 py-8 text-center">
                <svg class="w-8 h-8 text-muted mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="m21 21-4.35-4.35"/></svg>
                <p class="text-2xs text-muted">No audits yet</p>
            </div>
            @endforelse
        </div>
    </div>

    @endif
</aside>
