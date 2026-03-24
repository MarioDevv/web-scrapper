{{-- ══ TAB BAR + SEARCH + EXPORT ══ --}}
<nav class="flex-none flex items-center bg-panel2 border-b border-line gap-0.5 px-1">
    @php
        $tabs = [
            'overview'  => ['Overview',  null],
            'all'       => ['All',       $this->tabCounts['all']],
            'internal'  => ['Internal',  $this->tabCounts['internal']],
            'external'  => ['External',  $this->tabCounts['external']],
            'html'      => ['HTML',      $this->tabCounts['html']],
            'redirects' => ['3xx',       $this->tabCounts['redirects']],
            'errors'    => ['4xx/5xx',   $this->tabCounts['errors']],
            'issues'    => ['Issues',    $this->tabCounts['issues']],
            'noindex'   => ['Noindex',   $this->tabCounts['noindex']],
        ];
    @endphp
    @foreach($tabs as $key => [$label, $count])
    <button wire:click="setTab('{{ $key }}')"
        class="relative px-3 py-2 text-[13px] font-medium transition-all duration-100
            {{ $activeTab === $key ? 'text-primary' : 'text-tertiary hover:text-secondary' }}">
        {{ $label }}
        @if($count !== null && $count > 0)
            <span class="ml-1 text-2xs tabular-nums px-1.5 py-[1px] rounded
                {{ $activeTab === $key ? 'bg-accent-s c-accent' : 'bg-panel3 text-tertiary' }}">{{ $count }}</span>
        @endif
        @if($activeTab === $key)
            <span class="absolute bottom-0 left-2 right-2 h-[2px] rounded-full" style="background:var(--c-accent)"></span>
        @endif
    </button>
    @endforeach

    {{-- Search + Export --}}
    <div class="ml-auto flex items-center gap-2 pr-2">
        @if($activeTab !== 'external')
        <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
            <input type="text" wire:model.live.debounce.300ms="searchQuery"
                placeholder="Filtrar páginas…"
                class="h-7 w-40 bg-app2 border border-line rounded-md pl-7 pr-2 text-2xs text-primary placeholder:text-muted focus:border-[var(--c-accent)] focus:w-56 transition-all duration-200">
        </div>
        @endif

        {{-- CSV Export --}}
        @if(count($pages) > 0 || count($externalLinks) > 0)
        @if($activeTab === 'external')
            <button wire:click="exportExternalCsv" class="h-7 px-2.5 rounded-md bg-panel3 border border-line text-2xs text-secondary hover:text-primary hover:border-line2 transition-all flex items-center gap-1.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                CSV
            </button>
        @else
            <button wire:click="exportCsv" class="h-7 px-2.5 rounded-md bg-panel3 border border-line text-2xs text-secondary hover:text-primary hover:border-line2 transition-all flex items-center gap-1.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                CSV
            </button>
        @endif
        @endif
    </div>
</nav>

{{-- ══ OVERVIEW PANEL ══ --}}
@if($activeTab === 'overview')
<div class="flex-1 overflow-auto min-h-0">
    @php $ov = $this->overview; @endphp
    @if(!empty($ov))
    <div class="p-4 space-y-5 max-w-5xl">

        {{-- Summary cards --}}
        <div class="grid grid-cols-5 gap-3">
            @foreach([
                ['Páginas', $ov['totalPages'], null],
                ['Externos', $ov['totalExternal'], null],
                ['Problemas', $ov['totalIssues'], $ov['totalIssues'] > 0 ? 'var(--c-warn)' : 'var(--c-ok)'],
                ['Tiempo medio', $ov['avgResponseTime'] . 'ms', $ov['avgResponseTime'] > 1000 ? 'var(--c-warn)' : null],
                ['Imágenes', $ov['totalImages'], null],
            ] as [$ovLabel, $ovVal, $ovColor])
            <div class="bg-app2 rounded-lg px-3 py-2.5 border border-line">
                <div class="text-2xs text-tertiary mb-1">{{ $ovLabel }}</div>
                <div class="text-[18px] font-bold tabular-nums leading-none" style="{{ $ovColor ? "color:{$ovColor}" : '' }}">{{ $ovVal }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-3 gap-5">
            {{-- Status codes --}}
            <div class="bg-app2 rounded-lg border border-line p-3">
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-3">Códigos de respuesta</div>
                @php $maxSc = max(1, max($ov['statusGroups'])); @endphp
                <div class="space-y-2">
                    @foreach($ov['statusGroups'] as $code => $cnt)
                    @php $scColor = match($code) { '2xx' => 'var(--c-ok)', '3xx' => 'var(--c-warn)', default => 'var(--c-err)' }; @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-[12px] font-mono text-tertiary w-8">{{ $code }}</span>
                        <div class="flex-1 h-4 bg-app3 rounded overflow-hidden">
                            <div class="h-full rounded" style="width:{{ ($cnt / $maxSc) * 100 }}%;background:{{ $scColor }};opacity:0.7"></div>
                        </div>
                        <span class="text-[12px] font-mono tabular-nums text-secondary w-8 text-right">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Response times --}}
            <div class="bg-app2 rounded-lg border border-line p-3">
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-3">Tiempos de respuesta</div>
                @php $maxRt = max(1, max($ov['responseTimeBuckets'])); @endphp
                <div class="space-y-2">
                    @foreach($ov['responseTimeBuckets'] as $bucket => $cnt)
                    @php $rtColor = match($bucket) { '<200ms' => 'var(--c-ok)', '200-500ms' => 'var(--c-ok)', '500ms-1s' => 'var(--c-warn)', default => 'var(--c-err)' }; @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-mono text-tertiary w-16 truncate">{{ $bucket }}</span>
                        <div class="flex-1 h-4 bg-app3 rounded overflow-hidden">
                            <div class="h-full rounded" style="width:{{ ($cnt / $maxRt) * 100 }}%;background:{{ $rtColor }};opacity:0.7"></div>
                        </div>
                        <span class="text-[12px] font-mono tabular-nums text-secondary w-8 text-right">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Depth --}}
            <div class="bg-app2 rounded-lg border border-line p-3">
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-3">Profundidad</div>
                @php $maxDp = max(1, !empty($ov['depthDistribution']) ? max($ov['depthDistribution']) : 1); @endphp
                <div class="space-y-2">
                    @foreach($ov['depthDistribution'] as $depth => $cnt)
                    <div class="flex items-center gap-2">
                        <span class="text-[12px] font-mono text-tertiary w-8">d={{ $depth }}</span>
                        <div class="flex-1 h-4 bg-app3 rounded overflow-hidden">
                            <div class="h-full rounded" style="width:{{ ($cnt / $maxDp) * 100 }}%;background:var(--c-accent);opacity:0.6"></div>
                        </div>
                        <span class="text-[12px] font-mono tabular-nums text-secondary w-8 text-right">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            {{-- Issues by category --}}
            <div class="bg-app2 rounded-lg border border-line p-3">
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-3">Problemas por categoría</div>
                @if(!empty($ov['issuesByCategory']))
                @php $maxIc = max(1, max($ov['issuesByCategory'])); @endphp
                <div class="space-y-2">
                    @foreach($ov['issuesByCategory'] as $cat => $cnt)
                    <div class="flex items-center gap-2 cursor-pointer hover:bg-panel3 rounded px-1 -mx-1" wire:click="setTab('issues')">
                        <span class="text-[12px] text-tertiary w-24 truncate capitalize">{{ $cat }}</span>
                        <div class="flex-1 h-4 bg-app3 rounded overflow-hidden">
                            <div class="h-full rounded" style="width:{{ ($cnt / $maxIc) * 100 }}%;background:var(--c-warn);opacity:0.7"></div>
                        </div>
                        <span class="text-[12px] font-mono tabular-nums text-secondary w-8 text-right">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="flex items-center gap-2 py-4 justify-center text-tertiary">
                    <svg class="w-4 h-4" style="color:var(--c-ok);opacity:0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-[13px]">Sin problemas</span>
                </div>
                @endif
            </div>

            {{-- SEO quick check --}}
            <div class="bg-app2 rounded-lg border border-line p-3">
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-3">Verificación SEO</div>
                <div class="space-y-2">
                    @foreach([
                        ['Sin título', $ov['pagesWithoutTitle'], $ov['totalPages']],
                        ['Sin meta description', $ov['pagesWithoutDesc'], $ov['totalPages']],
                        ['Sin H1', $ov['pagesWithoutH1'], $ov['totalPages']],
                        ['Errores (4xx/5xx)', $ov['statusGroups']['4xx'] + $ov['statusGroups']['5xx'], $ov['totalPages']],
                        ['Redirecciones (3xx)', $ov['statusGroups']['3xx'], $ov['totalPages']],
                    ] as [$checkLabel, $checkBad, $checkTotal])
                    @php $checkPct = $checkTotal > 0 ? round(($checkBad / $checkTotal) * 100) : 0; @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-[12px] text-tertiary w-40 truncate">{{ $checkLabel }}</span>
                        <div class="flex-1 h-4 bg-app3 rounded overflow-hidden">
                            <div class="h-full rounded" style="width:{{ max($checkPct, 1) }}%;background:{{ $checkBad > 0 ? 'var(--c-err)' : 'var(--c-ok)' }};opacity:0.6"></div>
                        </div>
                        <span class="text-[12px] font-mono tabular-nums w-16 text-right {{ $checkBad > 0 ? 'c-err font-medium' : 'text-tertiary' }}">{{ $checkBad }}/{{ $checkTotal }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @elseif($auditId && $crawling)
    <div class="flex flex-col items-center justify-center h-full gap-4">
        <div class="w-12 h-12 rounded-xl bg-accent-s flex items-center justify-center">
            <div class="w-6 h-6 border-2 border-line rounded-full animate-spin" style="border-top-color:var(--c-accent)"></div>
        </div>
        <p class="text-[14px] text-secondary">Rastreando… el resumen aparecerá cuando termine</p>
    </div>
    @else
    <div class="flex items-center justify-center h-full text-muted text-[14px]">Lanza un crawl para ver el resumen</div>
    @endif
</div>

{{-- ══ EXTERNAL LINKS TABLE ══ --}}
@elseif($activeTab === 'external')
<div class="flex-1 overflow-auto min-h-0">
    @if(count($externalLinks) > 0)
    <div class="overflow-x-auto">
    <table class="w-full text-[13px] border-collapse" style="min-width:900px">
        <thead class="sticky top-0 z-10">
            <tr class="bg-panel2 border-b border-line">
                <th class="text-left px-3 py-2 font-medium text-tertiary text-2xs uppercase tracking-wider min-w-[300px]">URL Externa</th>
                <th class="text-center px-2 py-2 font-medium text-tertiary text-2xs uppercase tracking-wider w-16">Estado</th>
                <th class="text-right px-2 py-2 font-medium text-tertiary text-2xs uppercase tracking-wider w-16">Tiempo</th>
                <th class="text-left px-2 py-2 font-medium text-tertiary text-2xs uppercase tracking-wider min-w-[120px]">Anchor</th>
                <th class="text-left px-2 py-2 font-medium text-tertiary text-2xs uppercase tracking-wider min-w-[200px]">Página origen</th>
                <th class="text-left px-2 py-2 font-medium text-tertiary text-2xs uppercase tracking-wider">Error</th>
            </tr>
        </thead>
        <tbody>
            @foreach($externalLinks as $ext)
            <tr class="border-b border-line row-hover">
                <td class="px-3 py-[7px] font-mono text-[12px] text-secondary truncate max-w-0">{{ $ext['url'] }}</td>
                <td class="px-2 py-[7px] text-center">
                    @if($ext['status_code'] && $ext['status_code'] > 0)
                        @php $esc = (int)$ext['status_code']; @endphp
                        <span class="badge font-mono text-[11px]
                            {{ $esc >= 200 && $esc < 300 ? 'badge-ok' : ($esc >= 300 && $esc < 400 ? 'badge-warn' : 'badge-err') }}">{{ $esc }}</span>
                    @else
                        <span class="badge badge-err font-mono text-[11px]">Err</span>
                    @endif
                </td>
                <td class="px-2 py-[7px] text-right font-mono text-[12px] text-tertiary tabular-nums">
                    {{ $ext['response_time'] ? number_format($ext['response_time'], 0) . 'ms' : '—' }}
                </td>
                <td class="px-2 py-[7px] text-secondary text-[12px] truncate max-w-0">{{ $ext['anchor_text'] ?? '—' }}</td>
                <td class="px-2 py-[7px] font-mono text-[11px] text-muted truncate max-w-0">{{ $ext['source_url'] ?? '' }}</td>
                <td class="px-2 py-[7px] text-[11px] c-err truncate max-w-0">{{ $ext['error'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @elseif($auditId && $crawling)
    <div class="flex flex-col items-center justify-center h-full gap-4">
        <div class="w-12 h-12 rounded-xl bg-accent-s flex items-center justify-center">
            <div class="w-6 h-6 border-2 border-line rounded-full animate-spin" style="border-top-color:var(--c-accent)"></div>
        </div>
        <p class="text-[14px] text-secondary">Verificando enlaces externos…</p>
    </div>
    @elseif($auditId)
    <div class="flex items-center justify-center h-full text-tertiary text-[14px]">No se encontraron enlaces externos</div>
    @else
    <div class="flex items-center justify-center h-full text-muted text-[14px]">Lanza un crawl para verificar enlaces externos</div>
    @endif
</div>

@else
{{-- ══ PAGES TABLE ══ --}}
<div class="flex-1 overflow-auto min-h-0">
    @if(count($this->filteredPages) > 0)
    <div class="overflow-x-auto">
    <table class="w-full text-[13px] border-collapse" style="min-width:1200px">
        <thead class="sticky top-0 z-10">
            <tr class="bg-panel2 border-b border-line" style="backdrop-filter:blur(8px)">
                @php
                    $cols = [
                        ['url',               'URL',         'text-left',   'px-3 min-w-[260px]'],
                        ['statusCode',        'Estado',      'text-center', 'px-2 w-16'],
                        ['title',             'Título',      'text-left',   'px-2 min-w-[180px]'],
                        ['wordCount',         'Palabras',    'text-right',  'px-2 w-[70px]'],
                        ['h1Count',           'H1',          'text-center', 'px-2 w-10'],
                        ['internalLinkCount', 'Int.',        'text-right',  'px-2 w-12'],
                        ['externalLinkCount', 'Ext.',        'text-right',  'px-2 w-12'],
                        ['imageCount',        'Imgs',        'text-right',  'px-2 w-12'],
                        ['canonicalStatus',   'Canonical',   'text-center', 'px-2 w-[90px]'],
                        ['bodySize',          'Tamaño',      'text-right',  'px-2 w-16'],
                        ['responseTime',      'Tiempo',      'text-right',  'px-2 w-16'],
                        ['crawlDepth',        'Prof.',       'text-center', 'px-2 w-12'],
                        ['errorCount',        'Problemas',   'text-center', 'px-2 w-20'],
                        ['isIndexable',       'Idx',         'text-center', 'px-2 w-10'],
                    ];
                @endphp
                @foreach($cols as [$field, $label, $align, $classes])
                <th wire:click="toggleSort('{{ $field }}')"
                    class="{{ $align }} {{ $classes }} py-2 font-medium text-tertiary text-2xs uppercase tracking-wider cursor-pointer hover:text-secondary select-none transition-colors group whitespace-nowrap">
                    <span class="inline-flex items-center gap-1">
                        {{ $label }}
                        @if($sortField === $field)
                            <svg class="w-3 h-3 c-accent {{ $sortDir === 'desc' ? 'rotate-180' : '' }} transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 15l7-7 7 7"/></svg>
                        @else
                            <svg class="w-3 h-3 text-muted opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                        @endif
                    </span>
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($this->filteredPages as $idx => $page)
            @php $isExt = !empty($page['isExternalCheck']); @endphp
            <tr @if(!$isExt) wire:click="selectPage('{{ $page['pageId'] }}')" @endif
                wire:key="row-{{ $page['pageId'] ?? 'ext-'.$idx }}"
                class="border-b border-line
                       {{ $isExt ? 'row-hover opacity-70' : 'cursor-pointer row-hover' }}
                       {{ !$isExt && $selectedPageId === $page['pageId'] ? 'row-selected' : '' }}
                       {{ !$isExt && in_array($page['pageId'], $newPageIds) ? 'anim-fade' : '' }}">

                <td class="px-3 py-[7px] font-mono text-[12px] truncate max-w-0">
                    @if($isExt)<span class="badge text-[9px] mr-1" style="background:var(--c-info-bg,#dbeafe);color:var(--c-info,#3b82f6)">EXT</span>@endif
                    <span class="text-muted">{{ parse_url($page['url'], PHP_URL_HOST) }}</span><span class="text-secondary">{{ parse_url($page['url'], PHP_URL_PATH) ?: '/' }}</span>
                </td>

                <td class="px-2 py-[7px] text-center">
                    @php $sc = $page['statusCode']; @endphp
                    @if($sc > 0)
                    <span class="badge font-mono text-[11px]
                        {{ $sc >= 200 && $sc < 300 ? 'badge-ok' : ($sc >= 300 && $sc < 400 ? 'badge-warn' : 'badge-err') }}">{{ $sc }}</span>
                    @else
                    <span class="badge badge-err font-mono text-[11px]">Err</span>
                    @endif
                </td>

                <td class="px-2 py-[7px] text-secondary truncate max-w-0">{{ $page['title'] ?? '' }}</td>

                <td class="px-2 py-[7px] text-right font-mono text-[12px] tabular-nums text-tertiary">
                    {{ !$isExt && $page['wordCount'] > 0 ? $page['wordCount'] : '—' }}
                </td>

                <td class="px-2 py-[7px] text-center text-[12px] tabular-nums">
                    @if($isExt)
                        <span class="text-muted">—</span>
                    @elseif($page['h1Count'] === 0)
                        <span class="c-err font-medium">0</span>
                    @elseif($page['h1Count'] > 1)
                        <span class="c-warn font-medium">{{ $page['h1Count'] }}</span>
                    @else
                        <span class="text-tertiary">{{ $page['h1Count'] }}</span>
                    @endif
                </td>

                <td class="px-2 py-[7px] text-right font-mono text-[12px] tabular-nums text-tertiary">{{ $isExt ? '—' : $page['internalLinkCount'] }}</td>
                <td class="px-2 py-[7px] text-right font-mono text-[12px] tabular-nums text-tertiary">{{ $isExt ? '—' : $page['externalLinkCount'] }}</td>
                <td class="px-2 py-[7px] text-right font-mono text-[12px] tabular-nums text-tertiary">{{ $isExt ? '—' : $page['imageCount'] }}</td>

                <td class="px-2 py-[7px] text-center">
                    @if($isExt)
                        <span class="text-muted text-[11px]">—</span>
                    @else
                        @php $cs = $page['canonicalStatus']; @endphp
                        <span class="text-[11px] font-medium {{ match($cs) { 'self' => 'c-ok', 'other' => 'c-warn', default => 'c-err' } }}">
                            {{ match($cs) { 'self' => 'Auto-ref.', 'other' => 'Canonical.', default => 'Falta' } }}
                        </span>
                    @endif
                </td>

                <td class="px-2 py-[7px] text-right font-mono text-[12px] text-tertiary tabular-nums">
                    @if($isExt) — @else
                    {{ $page['bodySize'] > 1048576 ? number_format($page['bodySize']/1048576,1).'M'
                        : ($page['bodySize'] > 1024 ? number_format($page['bodySize']/1024,0).'K' : $page['bodySize'].'B') }}
                    @endif
                </td>

                <td class="px-2 py-[7px] text-right font-mono text-[12px] tabular-nums"
                    style="color:{{ $page['responseTime'] > 2000 ? 'var(--c-err)' : ($page['responseTime'] > 1000 ? 'var(--c-warn)' : 'var(--c-fg3)') }}">
                    @if($page['responseTime'] > 0)
                    {{ number_format($page['responseTime'], 0) }}<span class="text-muted">ms</span>
                    @else — @endif
                </td>

                <td class="px-2 py-[7px] text-center text-tertiary tabular-nums">{{ $isExt ? '—' : $page['crawlDepth'] }}</td>

                <td class="px-2 py-[7px] text-center">
                    @if($isExt)
                        @if($page['errorCount'] > 0)<span class="c-err font-medium text-2xs">Err</span>
                        @else <span class="text-muted text-2xs">—</span> @endif
                    @else
                        @if($page['errorCount'] > 0)<span class="c-err font-medium text-2xs">{{ $page['errorCount'] }}E</span>@endif
                        @if($page['warningCount'] > 0)<span class="c-warn font-medium text-2xs {{ $page['errorCount'] > 0 ? 'ml-0.5' : '' }}">{{ $page['warningCount'] }}W</span>@endif
                        @if($page['errorCount'] === 0 && $page['warningCount'] === 0)
                            <svg class="w-3.5 h-3.5 mx-auto" style="color:var(--c-ok);opacity:0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                        @endif
                    @endif
                </td>

                <td class="px-2 py-[7px] text-center">
                    @if($isExt)
                        <span class="text-muted text-[11px]">—</span>
                    @else
                        <span class="w-2.5 h-2.5 rounded-full inline-block"
                            style="background:{{ $page['isIndexable'] ? 'var(--c-ok)' : 'var(--c-err)' }};opacity:{{ $page['isIndexable'] ? '0.6' : '0.4' }}"></span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    @elseif($auditId && $crawling)
    <div class="flex flex-col items-center justify-center h-full gap-4">
        <div class="w-12 h-12 rounded-xl bg-accent-s flex items-center justify-center">
            <div class="w-6 h-6 border-2 border-line rounded-full animate-spin" style="border-top-color:var(--c-accent)"></div>
        </div>
        <div class="text-center">
            <p class="text-[14px] text-secondary font-medium">Descubriendo páginas…</p>
            <p class="text-2xs text-muted mt-1">Las páginas aparecerán aquí a medida que se rastreen</p>
        </div>
    </div>

    @elseif($auditId)
    <div class="flex items-center justify-center h-full text-tertiary text-[14px]">No hay páginas que coincidan con este filtro</div>

    @else
    <div class="flex flex-col items-center justify-center h-full gap-4">
        <div class="w-16 h-16 rounded-2xl bg-panel2 flex items-center justify-center border border-line">
            <svg class="w-8 h-8 text-muted opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="6"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                <path d="M11 8v6m-3-3h6" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="text-center">
            <p class="text-[14px] text-secondary">Introduce una URL y pulsa <span class="c-accent font-semibold">Crawl</span> para empezar</p>
            <p class="text-2xs text-muted mt-1">Las páginas aparecerán en tiempo real</p>
        </div>
    </div>
    @endif
</div>
@endif
