{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  TOP NAV — Segmented control (Dashboard vs Pages) + filter tabs + search  --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@php
    $isOverview = $activeTab === 'overview';
    $isAudit = $activeTab === 'audit';
    $inPages = !$isOverview && !$isAudit;
@endphp

<nav class="flex-none bg-panel2 border-b border-line chrome-nosel">

    {{-- ── MODE SWITCH ──────────────────────────────────────────── --}}
    <div class="flex items-center h-9 px-1 gap-0.5 border-b border-line">

        {{-- [ overview ] --}}
        <button wire:click="setTab('overview')"
                class="group h-7 px-3 flex items-center gap-1 text-[11px] font-mono uppercase tracking-[0.14em] transition-colors
                       {{ $isOverview ? 'c-accent' : 'text-tertiary hover:text-secondary' }}">
            <span class="{{ $isOverview ? 'text-muted' : 'text-muted group-hover:text-tertiary' }}">[</span>
            <span class="flex items-center gap-1.5">
                @if($isOverview)
                    <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent); box-shadow:0 0 6px var(--c-accent-glow);"></span>
                @endif
                overview
            </span>
            <span class="{{ $isOverview ? 'text-muted' : 'text-muted group-hover:text-tertiary' }}">]</span>
        </button>

        {{-- [ audit ] --}}
        <button wire:click="setTab('audit')"
                class="group h-7 px-3 flex items-center gap-1 text-[11px] font-mono uppercase tracking-[0.14em] transition-colors
                       {{ $isAudit ? 'c-accent' : 'text-tertiary hover:text-secondary' }}">
            <span class="{{ $isAudit ? 'text-muted' : 'text-muted group-hover:text-tertiary' }}">[</span>
            <span class="flex items-center gap-1.5">
                @if($isAudit)
                    <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent); box-shadow:0 0 6px var(--c-accent-glow);"></span>
                @endif
                audit
                @if(($this->tabCounts['issues'] ?? 0) > 0)
                    <span class="text-muted font-normal">·{{ $this->tabCounts['issues'] }}</span>
                @endif
            </span>
            <span class="{{ $isAudit ? 'text-muted' : 'text-muted group-hover:text-tertiary' }}">]</span>
        </button>

        {{-- [ pages ] --}}
        <button wire:click="setTab('all')"
                class="group h-7 px-3 flex items-center gap-1 text-[11px] font-mono uppercase tracking-[0.14em] transition-colors
                       {{ $inPages ? 'c-accent' : 'text-tertiary hover:text-secondary' }}">
            <span class="{{ $inPages ? 'text-muted' : 'text-muted group-hover:text-tertiary' }}">[</span>
            <span class="flex items-center gap-1.5">
                @if($inPages)
                    <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent); box-shadow:0 0 6px var(--c-accent-glow);"></span>
                @endif
                pages
                <span class="text-muted font-normal">·{{ $this->tabCounts['all'] }}</span>
            </span>
            <span class="{{ $inPages ? 'text-muted' : 'text-muted group-hover:text-tertiary' }}">]</span>
        </button>

        <span class="h-4 w-px mx-2" style="background: var(--c-border);"></span>

        {{-- Filter chip summary when in pages mode --}}
        @if($inPages && $activeTab !== 'all')
            <span class="font-mono text-[10px] text-tertiary flex items-center gap-1.5">
                <span class="text-muted">filter:</span>
                <span class="c-accent">{{ $activeTab }}</span>
                <button wire:click="setTab('all')" class="text-muted hover:c-err transition-colors" title="Clear filter">✕</button>
            </span>
        @endif

        <div class="flex-1"></div>

        {{-- Search + export (pages mode only) --}}
        @if($inPages)
            @if($activeTab !== 'external')
                <label class="relative flex items-center bg-app2 border border-line h-7 pl-2.5 pr-2 gap-1.5 focus-within:border-line3 transition-colors">
                    <span class="c-accent font-mono text-[11px]">/</span>
                    <input type="text" wire:model.live.debounce.300ms="searchQuery"
                           placeholder="filter…"
                           class="w-36 bg-transparent text-[11px] font-mono text-primary placeholder:text-muted focus:w-48 transition-all duration-200">
                </label>
            @endif

            @if(count($pages) > 0 || count($externalLinks) > 0)
                @if($activeTab === 'external')
                    <button wire:click="exportExternalCsv"
                            class="h-7 px-2.5 bg-app2 border border-line text-tertiary hover:text-primary hover:border-line2 transition-colors flex items-center gap-1.5 text-[10px] font-mono uppercase tracking-[0.14em]">
                        <span>↓</span>
                        <span>csv</span>
                    </button>
                @else
                    <button wire:click="exportCsv"
                            class="h-7 px-2.5 bg-app2 border border-line text-tertiary hover:text-primary hover:border-line2 transition-colors flex items-center gap-1.5 text-[10px] font-mono uppercase tracking-[0.14em]">
                        <span>↓</span>
                        <span>csv</span>
                    </button>
                @endif
            @endif
        @endif
    </div>

    {{-- ── FILTER TABS (only in pages mode) ─────────────────────── --}}
    @if($inPages)
    @php
        $filters = [
            'all'       => ['all',      $this->tabCounts['all']],
            'internal'  => ['internal', $this->tabCounts['internal']],
            'external'  => ['external', $this->tabCounts['external']],
            'html'      => ['html',     $this->tabCounts['html']],
            'redirects' => ['3xx',      $this->tabCounts['redirects']],
            'errors'    => ['4xx/5xx',  $this->tabCounts['errors']],
            'issues'    => ['issues',   $this->tabCounts['issues']],
            'noindex'   => ['noindex',  $this->tabCounts['noindex']],
        ];
    @endphp
    <div class="flex items-center h-8 px-1 gap-0 overflow-x-auto">
        @foreach($filters as $key => [$label, $count])
        <button wire:click="setTab('{{ $key }}')"
                class="relative h-8 px-3 text-[11px] font-mono uppercase tracking-[0.14em] transition-colors whitespace-nowrap
                       {{ $activeTab === $key ? 'text-primary' : 'text-tertiary hover:text-secondary' }}">
            <span class="inline-flex items-center gap-1.5">
                @if($activeTab === $key)
                    <span class="c-accent">▸</span>
                @endif
                <span>{{ $label }}</span>
                @if($count !== null && $count > 0)
                    <span class="text-muted tabular-nums font-normal">·{{ $count }}</span>
                @endif
            </span>
            @if($activeTab === $key)
                <span class="absolute bottom-0 left-0 right-0 h-[2px]" style="background: var(--c-accent); box-shadow: 0 0 6px var(--c-accent-glow);"></span>
            @endif
        </button>
        @endforeach
    </div>
    @endif
</nav>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  AUDIT PANEL — Site-wide issues grouped by rule                            --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'audit')
    @include('livewire.partials.audit-report')

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  OVERVIEW PANEL — Terminal-style audit dashboard                           --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@elseif($activeTab === 'overview')
<div class="flex-1 overflow-auto min-h-0">
    @php $ov = $this->overview; @endphp

    @if(!empty($ov))
    <div class="p-5 space-y-5 max-w-6xl">

        {{-- ── SUMMARY BLOCK ─────────────────────────────────────── --}}
        <section class="bg-app2 border border-line">
            <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">── audit/summary ──────</span>
                <span class="font-mono text-[10px] text-muted">readonly</span>
            </div>
            <div class="grid grid-cols-6 divide-x divide-[var(--c-border)]">
                @php
                    $siteScore = $this->auditReport['siteScore'] ?? 100;
                    $siteScoreColor = $siteScore >= 90
                        ? 'var(--c-ok)'
                        : ($siteScore >= 50 ? 'var(--c-warn)' : 'var(--c-err)');
                    $summaryCards = [
                        ['score',      $siteScore,                           $siteScoreColor],
                        ['pages',      $ov['totalPages'],                    null],
                        ['external',   $ov['totalExternal'],                 null],
                        ['issues',     $ov['totalIssues'],                   $ov['totalIssues']    > 0 ? 'var(--c-warn)' : 'var(--c-ok)'],
                        ['avg_ms',     $ov['avgResponseTime'],               $ov['avgResponseTime'] > 1000 ? 'var(--c-warn)' : null],
                        ['images',     $ov['totalImages'],                   null],
                    ];
                @endphp
                @foreach($summaryCards as [$label, $value, $color])
                <div class="px-4 py-3 stat-card">
                    <div class="text-[10px] font-mono uppercase tracking-[0.16em] text-muted mb-1.5">{{ $label }}</div>
                    <div class="text-[22px] font-mono font-medium tabular-nums leading-none"
                         style="{{ $color ? "color: {$color}" : 'color: var(--c-fg)' }}">
                        {{ is_numeric($value) ? number_format($value) : $value }}
                    </div>
                </div>
                @endforeach
            </div>
        </section>

        {{-- ── CHARTS: codes / times / depth ─────────────────────── --}}
        <div class="grid grid-cols-3 gap-5">

            {{-- Response codes --}}
            @php $maxSc = max(1, max($ov['statusGroups'])); @endphp
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">response_codes</span>
                    <span class="font-mono text-[10px] text-muted">{{ array_sum($ov['statusGroups']) }}</span>
                </div>
                <div class="p-3 space-y-1.5">
                    @foreach($ov['statusGroups'] as $code => $cnt)
                    @php
                        $scColor = match($code) {
                            '2xx' => 'var(--c-ok)',
                            '3xx' => 'var(--c-warn)',
                            default => 'var(--c-err)',
                        };
                    @endphp
                    <div class="flex items-center gap-2 font-mono text-[11px]">
                        <span class="text-tertiary w-8 tabular-nums">{{ $code }}</span>
                        <div class="flex-1 h-3 progress-track">
                            <div class="h-full" style="width:{{ ($cnt / $maxSc) * 100 }}%; background:{{ $scColor }}; box-shadow: 0 0 4px {{ $scColor }};"></div>
                        </div>
                        <span class="text-secondary w-10 text-right tabular-nums">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
            </section>

            {{-- Response times --}}
            @php $maxRt = max(1, max($ov['responseTimeBuckets'])); @endphp
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">response_times</span>
                    <span class="font-mono text-[10px] text-muted">ms</span>
                </div>
                <div class="p-3 space-y-1.5">
                    @foreach($ov['responseTimeBuckets'] as $bucket => $cnt)
                    @php
                        $rtColor = match($bucket) {
                            '<200ms'    => 'var(--c-ok)',
                            '200-500ms' => 'var(--c-ok)',
                            '500ms-1s'  => 'var(--c-warn)',
                            default     => 'var(--c-err)',
                        };
                    @endphp
                    <div class="flex items-center gap-2 font-mono text-[11px]">
                        <span class="text-tertiary w-16 truncate">{{ $bucket }}</span>
                        <div class="flex-1 h-3 progress-track">
                            <div class="h-full" style="width:{{ ($cnt / $maxRt) * 100 }}%; background:{{ $rtColor }}; box-shadow: 0 0 4px {{ $rtColor }};"></div>
                        </div>
                        <span class="text-secondary w-10 text-right tabular-nums">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
            </section>

            {{-- Depth --}}
            @php $maxDp = max(1, !empty($ov['depthDistribution']) ? max($ov['depthDistribution']) : 1); @endphp
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">crawl_depth</span>
                    <span class="font-mono text-[10px] text-muted">hops</span>
                </div>
                <div class="p-3 space-y-1.5">
                    @foreach($ov['depthDistribution'] as $depth => $cnt)
                    <div class="flex items-center gap-2 font-mono text-[11px]">
                        <span class="text-tertiary w-8 tabular-nums">d={{ $depth }}</span>
                        <div class="flex-1 h-3 progress-track">
                            <div class="h-full" style="width:{{ ($cnt / $maxDp) * 100 }}%; background: var(--c-accent); box-shadow: 0 0 4px var(--c-accent-glow);"></div>
                        </div>
                        <span class="text-secondary w-10 text-right tabular-nums">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- ── ISSUES BY CATEGORY + SEO CHECK ────────────────────── --}}
        <div class="grid grid-cols-2 gap-5">

            {{-- Issues by category --}}
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">issues_by_category</span>
                    <button wire:click="setTab('issues')"
                            class="font-mono text-[10px] text-muted hover:c-accent transition-colors">
                        goto ↗
                    </button>
                </div>
                @if(!empty($ov['issuesByCategory']))
                    @php $maxIc = max(1, max($ov['issuesByCategory'])); @endphp
                    <div class="p-3 space-y-1.5">
                        @foreach($ov['issuesByCategory'] as $cat => $cnt)
                        <div wire:click="setTab('issues')"
                             class="flex items-center gap-2 font-mono text-[11px] cursor-pointer hover:bg-panel3 px-1 -mx-1 transition-colors">
                            <span class="text-tertiary w-24 truncate">{{ $cat }}</span>
                            <div class="flex-1 h-3 progress-track">
                                <div class="h-full" style="width:{{ ($cnt / $maxIc) * 100 }}%; background: var(--c-warn); box-shadow: 0 0 4px var(--c-warn);"></div>
                            </div>
                            <span class="text-secondary w-10 text-right tabular-nums">{{ $cnt }}</span>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center justify-center gap-2 py-6 font-mono text-[12px] c-ok">
                        <span>✓</span>
                        <span>no issues detected</span>
                    </div>
                @endif
            </section>

            {{-- SEO check --}}
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">seo_check</span>
                    <span class="font-mono text-[10px] text-muted">checks</span>
                </div>
                <div class="p-3 space-y-1.5">
                    @foreach([
                        ['missing_title',    $ov['pagesWithoutTitle'], $ov['totalPages']],
                        ['missing_desc',     $ov['pagesWithoutDesc'],  $ov['totalPages']],
                        ['missing_h1',       $ov['pagesWithoutH1'],    $ov['totalPages']],
                        ['errors_4xx_5xx',   $ov['statusGroups']['4xx'] + $ov['statusGroups']['5xx'], $ov['totalPages']],
                        ['redirects_3xx',    $ov['statusGroups']['3xx'], $ov['totalPages']],
                    ] as [$checkLabel, $checkBad, $checkTotal])
                    @php $checkPct = $checkTotal > 0 ? round(($checkBad / $checkTotal) * 100) : 0; @endphp
                    <div class="flex items-center gap-2 font-mono text-[11px]">
                        <span class="{{ $checkBad > 0 ? 'c-err' : 'c-ok' }} w-3">{{ $checkBad > 0 ? '✗' : '✓' }}</span>
                        <span class="text-tertiary w-40 truncate">{{ $checkLabel }}</span>
                        <div class="flex-1 h-3 progress-track">
                            <div class="h-full" style="width: {{ max($checkPct, 1) }}%;
                                                        background: {{ $checkBad > 0 ? 'var(--c-err)' : 'var(--c-ok)' }};
                                                        box-shadow: 0 0 4px {{ $checkBad > 0 ? 'var(--c-err)' : 'var(--c-ok)' }};"></div>
                        </div>
                        <span class="w-14 text-right tabular-nums {{ $checkBad > 0 ? 'c-err' : 'text-tertiary' }}">
                            {{ $checkBad }}/{{ $checkTotal }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    @elseif($auditId && $crawling)
    {{-- Crawling in progress, no overview yet --}}
    <div class="flex flex-col items-center justify-center h-full gap-3 font-mono">
        <div class="c-accent text-[14px] ellipsis">discovering</div>
        <div class="text-muted text-[11px] uppercase tracking-[0.16em]">summary appears when crawl completes</div>
    </div>

    @else
    <div class="flex items-center justify-center h-full font-mono text-[12px] text-muted">
        <span class="c-accent mr-2">$</span>
        start a crawl to see the summary
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  EXTERNAL LINKS TABLE                                                      --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@elseif($activeTab === 'external')
<div class="flex-1 overflow-auto min-h-0">
    @if(count($externalLinks) > 0)
    <div class="overflow-x-auto">
    <table class="w-full text-[12px] border-collapse font-mono" style="min-width:900px">
        <thead class="sticky top-0 z-10">
            <tr class="bg-panel2 border-b border-line2">
                <th class="text-left px-3 py-2 font-mono text-[10px] uppercase tracking-[0.14em] text-muted min-w-[300px]">external_url</th>
                <th class="text-center px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em] text-muted w-16">status</th>
                <th class="text-right px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em] text-muted w-16">t_ms</th>
                <th class="text-left px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em] text-muted min-w-[120px]">anchor</th>
                <th class="text-left px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em] text-muted min-w-[200px]">source_page</th>
                <th class="text-left px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em] text-muted">err</th>
            </tr>
        </thead>
        <tbody>
            @foreach($externalLinks as $ext)
            <tr class="border-b border-line row-hover">
                <td class="px-3 py-[6px] text-secondary truncate max-w-0">{{ $ext['url'] }}</td>
                <td class="px-2 py-[6px] text-center">
                    @if($ext['status_code'] && $ext['status_code'] > 0)
                        @php $esc = (int)$ext['status_code']; @endphp
                        <span class="badge text-[10px]
                            {{ $esc >= 200 && $esc < 300 ? 'badge-ok' : ($esc >= 300 && $esc < 400 ? 'badge-warn' : 'badge-err') }}">{{ $esc }}</span>
                    @else
                        <span class="badge badge-err text-[10px]">err</span>
                    @endif
                </td>
                <td class="px-2 py-[6px] text-right text-[11px] text-tertiary tabular-nums">
                    {{ $ext['response_time'] ? number_format($ext['response_time'], 0) : '—' }}
                </td>
                <td class="px-2 py-[6px] text-secondary text-[11px] truncate max-w-0">{{ $ext['anchor_text'] ?? '—' }}</td>
                <td class="px-2 py-[6px] text-[11px] text-muted truncate max-w-0">{{ $ext['source_url'] ?? '' }}</td>
                <td class="px-2 py-[6px] text-[11px] c-err truncate max-w-0">{{ $ext['error'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @elseif($auditId && $crawling)
    <div class="flex flex-col items-center justify-center h-full gap-3 font-mono">
        <div class="c-accent text-[14px] ellipsis">verifying external links</div>
        <div class="text-muted text-[11px] uppercase tracking-[0.16em]">HEAD requests in flight</div>
    </div>
    @elseif($auditId)
    <div class="flex items-center justify-center h-full font-mono text-[12px] text-muted">
        <span class="c-accent mr-2">$</span>no external links found
    </div>
    @else
    <div class="flex items-center justify-center h-full font-mono text-[12px] text-muted">
        <span class="c-accent mr-2">$</span>start a crawl to verify external links
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  PAGES TABLE                                                               --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@else
<div class="flex-1 overflow-auto min-h-0" style="contain: layout style paint;">
    @if(count($this->filteredPages) > 0)
    <div class="overflow-x-auto">
    <table class="w-full text-[12px] border-collapse font-mono" style="min-width:1200px; contain: layout style;">
        <thead class="sticky top-0 z-10">
            <tr class="bg-panel2 border-b border-line2">
                @php
                    $cols = [
                        ['url',               'url',      'text-left',   'px-3 min-w-[260px]'],
                        ['statusCode',        'status',   'text-center', 'px-2 w-16'],
                        ['title',             'title',    'text-left',   'px-2 min-w-[180px]'],
                        ['wordCount',         'words',    'text-right',  'px-2 w-[70px]'],
                        ['h1Count',           'h1',       'text-center', 'px-2 w-10'],
                        ['internalLinkCount', 'int',      'text-right',  'px-2 w-12'],
                        ['externalLinkCount', 'ext',      'text-right',  'px-2 w-12'],
                        ['imageCount',        'img',      'text-right',  'px-2 w-12'],
                        ['canonicalStatus',   'canon',    'text-center', 'px-2 w-[90px]'],
                        ['bodySize',          'size',     'text-right',  'px-2 w-16'],
                        ['responseTime',      't_ms',     'text-right',  'px-2 w-16'],
                        ['crawlDepth',        'depth',    'text-center', 'px-2 w-12'],
                        ['errorCount',        'iss',      'text-center', 'px-2 w-20'],
                        ['isIndexable',       'idx',      'text-center', 'px-2 w-10'],
                    ];
                @endphp
                @foreach($cols as [$field, $label, $align, $classes])
                <th wire:click="toggleSort('{{ $field }}')"
                    class="{{ $align }} {{ $classes }} py-2 font-mono text-[10px] uppercase tracking-[0.14em] cursor-pointer select-none whitespace-nowrap transition-colors group
                           {{ $sortField === $field ? 'c-accent' : 'text-muted hover:text-tertiary' }}">
                    <span class="inline-flex items-center gap-1">
                        {{ $label }}
                        @if($sortField === $field)
                            <span class="c-accent text-[9px]">{{ $sortDir === 'desc' ? '↓' : '↑' }}</span>
                        @else
                            <span class="text-muted opacity-0 group-hover:opacity-100 transition-opacity text-[9px]">↕</span>
                        @endif
                    </span>
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($this->filteredPages as $idx => $page)
            @php
                $isExt     = !empty($page['isExternalCheck']);
                $isSel     = !$isExt && $selectedPageId === $page['pageId'];
                $isNew     = !$isExt && in_array($page['pageId'], $newPageIds);
            @endphp
            <tr @if(!$isExt) wire:click="selectPage('{{ $page['pageId'] }}')" @endif
                wire:key="row-{{ $page['pageId'] ?? 'ext-'.$idx }}"
                style="content-visibility: auto; contain-intrinsic-size: auto 32px;"
                class="border-b border-line
                       {{ $isExt ? 'row-hover opacity-70' : 'cursor-pointer row-hover' }}
                       {{ $isSel ? 'row-selected' : '' }}
                       {{ $isNew ? 'row-new' : '' }}">

                {{-- URL --}}
                <td class="px-3 py-[6px] truncate max-w-0">
                    <span class="inline-flex items-center gap-1.5 w-full">
                        @if($isSel)
                            <span class="c-accent shrink-0">▸</span>
                        @elseif($isExt)
                            <span class="badge badge-info text-[9px] shrink-0">ext</span>
                        @else
                            <span class="text-muted shrink-0 opacity-0">▸</span>
                        @endif
                        <span class="truncate">
                            <span class="text-muted">{{ parse_url($page['url'], PHP_URL_HOST) }}</span><span class="text-secondary">{{ parse_url($page['url'], PHP_URL_PATH) ?: '/' }}</span>
                        </span>
                    </span>
                </td>

                {{-- Status --}}
                <td class="px-2 py-[6px] text-center">
                    @php $sc = $page['statusCode']; @endphp
                    @if($sc > 0)
                        <span class="badge text-[10px] {{ $sc >= 200 && $sc < 300 ? 'badge-ok' : ($sc >= 300 && $sc < 400 ? 'badge-warn' : 'badge-err') }}">{{ $sc }}</span>
                    @else
                        <span class="badge badge-err text-[10px]">err</span>
                    @endif
                </td>

                {{-- Title --}}
                <td class="px-2 py-[6px] text-secondary truncate max-w-0 font-sans text-[12px]">{{ $page['title'] ?? '' }}</td>

                {{-- Words --}}
                <td class="px-2 py-[6px] text-right text-[11px] tabular-nums text-tertiary">
                    {{ !$isExt && $page['wordCount'] > 0 ? number_format($page['wordCount']) : '—' }}
                </td>

                {{-- H1 --}}
                <td class="px-2 py-[6px] text-center text-[11px] tabular-nums">
                    @if($isExt)
                        <span class="text-muted">—</span>
                    @elseif($page['h1Count'] === 0)
                        <span class="c-err">0</span>
                    @elseif($page['h1Count'] > 1)
                        <span class="c-warn">{{ $page['h1Count'] }}</span>
                    @else
                        <span class="text-tertiary">{{ $page['h1Count'] }}</span>
                    @endif
                </td>

                {{-- Int / Ext / Img --}}
                <td class="px-2 py-[6px] text-right text-[11px] tabular-nums text-tertiary">{{ $isExt ? '—' : $page['internalLinkCount'] }}</td>
                <td class="px-2 py-[6px] text-right text-[11px] tabular-nums text-tertiary">{{ $isExt ? '—' : $page['externalLinkCount'] }}</td>
                <td class="px-2 py-[6px] text-right text-[11px] tabular-nums text-tertiary">{{ $isExt ? '—' : $page['imageCount'] }}</td>

                {{-- Canonical --}}
                <td class="px-2 py-[6px] text-center">
                    @if($isExt)
                        <span class="text-muted text-[11px]">—</span>
                    @else
                        @php $cs = $page['canonicalStatus']; @endphp
                        <span class="text-[10px] {{ match($cs) { 'self' => 'c-ok', 'other' => 'c-warn', default => 'c-err' } }}">
                            {{ match($cs) { 'self' => 'self', 'other' => 'canon', default => 'miss' } }}
                        </span>
                    @endif
                </td>

                {{-- Size --}}
                <td class="px-2 py-[6px] text-right text-[11px] text-tertiary tabular-nums">
                    @if($isExt) — @else
                        @php $b = $page['bodySize']; @endphp
                        {{ $b > 1048576 ? number_format($b/1048576,1).'M'
                         : ($b > 1024    ? number_format($b/1024,0).'K'
                         : $b.'B') }}
                    @endif
                </td>

                {{-- Response time --}}
                <td class="px-2 py-[6px] text-right text-[11px] tabular-nums"
                    style="color: {{ $page['responseTime'] > 2000 ? 'var(--c-err)' : ($page['responseTime'] > 1000 ? 'var(--c-warn)' : 'var(--c-fg3)') }};">
                    @if($page['responseTime'] > 0)
                        {{ number_format($page['responseTime'], 0) }}<span class="text-muted">ms</span>
                    @else — @endif
                </td>

                {{-- Depth --}}
                <td class="px-2 py-[6px] text-center text-[11px] text-tertiary tabular-nums">{{ $isExt ? '—' : $page['crawlDepth'] }}</td>

                {{-- Issues --}}
                <td class="px-2 py-[6px] text-center text-[11px] tabular-nums">
                    @if($isExt)
                        @if($page['errorCount'] > 0)
                            <span class="c-err">err</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    @else
                        @if($page['errorCount'] > 0)<span class="c-err">{{ $page['errorCount'] }}e</span>@endif
                        @if($page['warningCount'] > 0)<span class="c-warn {{ $page['errorCount'] > 0 ? 'ml-1' : '' }}">{{ $page['warningCount'] }}w</span>@endif
                        @if($page['errorCount'] === 0 && $page['warningCount'] === 0)
                            <span class="c-ok opacity-60">✓</span>
                        @endif
                    @endif
                </td>

                {{-- Indexable --}}
                <td class="px-2 py-[6px] text-center">
                    @if($isExt)
                        <span class="text-muted text-[11px]">—</span>
                    @else
                        <span class="text-[11px] font-mono" style="color:{{ $page['isIndexable'] ? 'var(--c-ok)' : 'var(--c-err)' }};">
                            {{ $page['isIndexable'] ? '●' : '○' }}
                        </span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    {{-- ── PAGINATION FOOTER ─────────────────────────────────────── --}}
    @php
        $totalPages = $pageSize > 0 ? (int) ceil($pagesTotal / $pageSize) : 1;
        $showPager = !$crawling && $pagesTotal > $pageSize;
    @endphp
    @if($showPager)
    <div class="flex items-center justify-between px-3 py-2 border-t border-line bg-app2 font-mono text-[10px] text-muted uppercase tracking-[0.16em]">
        <span>
            page <span class="text-primary tabular-nums">{{ $currentPage + 1 }}</span> /
            <span class="text-primary tabular-nums">{{ max(1, $totalPages) }}</span>
            <span class="text-tertiary">·</span>
            <span class="text-primary tabular-nums">{{ number_format($pagesTotal) }}</span> rows
        </span>
        <span class="flex items-center gap-2">
            <button wire:click="prevPage"
                    @disabled($currentPage === 0)
                    class="px-2 py-0.5 border border-line hover:border-primary disabled:opacity-30 disabled:cursor-not-allowed">
                ← prev
            </button>
            <button wire:click="nextPage"
                    @disabled(($currentPage + 1) * $pageSize >= $pagesTotal)
                    class="px-2 py-0.5 border border-line hover:border-primary disabled:opacity-30 disabled:cursor-not-allowed">
                next →
            </button>
        </span>
    </div>
    @endif

    @elseif($auditId && $crawling)
    <div class="flex flex-col items-center justify-center h-full gap-3 font-mono">
        <div class="c-accent text-[14px] ellipsis">discovering pages</div>
        <div class="text-muted text-[11px] uppercase tracking-[0.16em]">rows appear as they are crawled</div>
    </div>

    @elseif($auditId)
    <div class="flex items-center justify-center h-full font-mono text-[12px] text-muted">
        <span class="c-accent mr-2">$</span>no pages match this filter
    </div>

    @else
    {{-- Initial empty state: terminal welcome --}}
    <div class="flex flex-col items-center justify-center h-full gap-4 px-8">
        <pre class="font-mono text-[9px] c-accent leading-tight opacity-60 text-center select-none" style="text-shadow: 0 0 6px var(--c-accent-glow);">
                _            _     _
 ___  ___  ___  |  |___ ___ |_| ___| |___ ___
|_ -|| -_|| . | |__|_ -| . | | || . |   | -_|
|___||___||___||_|_|___|  _|_|_||___|_|_|___|
                       |_|
        </pre>
        <div class="text-center space-y-1 font-mono">
            <div class="text-[12px] text-secondary">
                <span class="c-accent">$</span> enter a url and press
                <span class="c-accent">[exec]</span> to begin crawl
            </div>
            <div class="text-[10px] text-muted uppercase tracking-[0.16em]">
                pages stream into this view in real time
            </div>
        </div>
    </div>
    @endif
</div>
@endif
