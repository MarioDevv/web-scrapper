{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  DETAIL PANEL — bottom split inspector                                     --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($detailOpen && $selectedPage)
<div class="flex-none border-t-2 border-line2 bg-panel anim-slide" style="height: 280px;">

    {{-- ── HEADER ─────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-3 px-3 h-9 border-b border-line bg-panel2 chrome-nosel">

        <div class="flex items-center gap-2.5 min-w-0">
            {{-- Status badge --}}
            @php $sc = $selectedPage['statusCode']; @endphp
            <span class="badge text-[10px] shrink-0
                {{ $sc >= 200 && $sc < 300 ? 'badge-ok' : ($sc >= 300 && $sc < 400 ? 'badge-warn' : 'badge-err') }}">
                {{ $sc }}
            </span>

            {{-- Prompt + URL --}}
            <span class="c-accent font-mono text-[12px] shrink-0" style="text-shadow: 0 0 4px var(--c-accent-glow);">▸</span>
            <span class="font-mono text-[12px] text-secondary truncate">{{ $selectedPage['url'] }}</span>

            {{-- Flag badges --}}
            <div class="flex items-center gap-1 shrink-0">
                @if($selectedPage['isIndexable']) <span class="badge badge-ok">indexable</span>   @endif
                @if($selectedPage['noindex'])     <span class="badge badge-err">noindex</span>    @endif
                @if($selectedPage['nofollow'])    <span class="badge badge-warn">nofollow</span>  @endif
            </div>
        </div>

        <button wire:click="closeDetail"
                class="shrink-0 h-7 w-7 flex items-center justify-center font-mono text-[12px] text-tertiary hover:c-err hover:bg-panel3 transition-colors"
                title="Close">
            ✕
        </button>
    </div>

    {{-- ── TAB STRIP ──────────────────────────────────────────── --}}
    @php
        $allLinks = $selectedPage['links'] ?? [];
        $intLinks = array_values(array_filter($allLinks, fn($l) => $l['internal'] && $l['type'] === 'anchor'));
        $extLinks = array_values(array_filter($allLinks, fn($l) => !$l['internal'] && $l['type'] === 'anchor'));
        $imgLinks = array_values(array_filter($allLinks, fn($l) => $l['type'] === 'image'));
        $detailTabs = [
            'seo'       => ['seo',       null],
            'technical' => ['technical', null],
            'links'     => ['links',     count($intLinks) . '/' . count($extLinks)],
            'issues'    => ['issues',    count($selectedPage['issues'])],
        ];
    @endphp
    <div class="flex items-center gap-0 h-8 border-b border-line bg-panel chrome-nosel">
        @foreach($detailTabs as $tKey => [$tLabel, $tCount])
        <button wire:click="setDetailTab('{{ $tKey }}')"
                class="relative h-full px-3 text-[11px] font-mono uppercase tracking-[0.14em] transition-colors
                       {{ $detailTab === $tKey ? 'text-primary' : 'text-tertiary hover:text-secondary' }}">
            <span class="inline-flex items-center gap-1.5">
                @if($detailTab === $tKey)
                    <span class="c-accent">▸</span>
                @endif
                <span>{{ $tLabel }}</span>
                @if($tCount !== null)
                    <span class="text-muted font-normal">·{{ $tCount }}</span>
                @endif
            </span>
            @if($detailTab === $tKey)
                <span class="absolute bottom-0 left-0 right-0 h-[2px]" style="background: var(--c-accent); box-shadow: 0 0 6px var(--c-accent-glow);"></span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- ── CONTENT ────────────────────────────────────────────── --}}
    <div class="h-[calc(280px-36px-32px)] overflow-y-auto">

        {{-- ═══ SEO ═══ --}}
        @if($detailTab === 'seo')
        <div class="p-4 space-y-4 anim-fade">

            {{-- Title --}}
            <div>
                <div class="flex items-center gap-2 mb-1.5 font-mono text-[10px] uppercase tracking-[0.16em]">
                    <span class="c-accent">▸</span>
                    <span class="text-muted">title</span>
                    @if($selectedPage['titleLength'])
                        @php $tl = $selectedPage['titleLength']; @endphp
                        @php $tlBad = $tl > 60 || $tl < 30; @endphp
                        <span class="text-tertiary">│</span>
                        <span style="color: {{ $tlBad ? 'var(--c-warn)' : 'var(--c-ok)' }};">{{ $tl }} chars</span>
                        <div class="flex-1 h-[3px] progress-track max-w-[140px]">
                            <div class="h-full" style="width: {{ min(($tl / 70) * 100, 100) }}%;
                                                        background: {{ $tlBad ? 'var(--c-warn)' : 'var(--c-ok)' }};
                                                        box-shadow: 0 0 4px {{ $tlBad ? 'var(--c-warn)' : 'var(--c-ok)' }};"></div>
                        </div>
                    @endif
                </div>
                <div class="font-sans text-[13px] text-primary leading-snug">{{ $selectedPage['title'] ?? '—' }}</div>
            </div>

            {{-- Meta description --}}
            <div>
                <div class="flex items-center gap-2 mb-1.5 font-mono text-[10px] uppercase tracking-[0.16em]">
                    <span class="c-accent">▸</span>
                    <span class="text-muted">meta_description</span>
                    @if($selectedPage['metaDescriptionLength'])
                        @php $ml = $selectedPage['metaDescriptionLength']; @endphp
                        @php $mlBad = $ml > 160 || $ml < 70; @endphp
                        <span class="text-tertiary">│</span>
                        <span style="color: {{ $mlBad ? 'var(--c-warn)' : 'var(--c-ok)' }};">{{ $ml }} chars</span>
                        <div class="flex-1 h-[3px] progress-track max-w-[140px]">
                            <div class="h-full" style="width: {{ min(($ml / 170) * 100, 100) }}%;
                                                        background: {{ $mlBad ? 'var(--c-warn)' : 'var(--c-ok)' }};
                                                        box-shadow: 0 0 4px {{ $mlBad ? 'var(--c-warn)' : 'var(--c-ok)' }};"></div>
                        </div>
                    @endif
                </div>
                <div class="font-sans text-[12px] text-secondary leading-relaxed">
                    {{ \Illuminate\Support\Str::limit($selectedPage['metaDescription'] ?? '—', 220) }}
                </div>
            </div>

            {{-- H1 + inline metrics --}}
            <div class="flex gap-6">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1.5 font-mono text-[10px] uppercase tracking-[0.16em]">
                        <span class="c-accent">▸</span>
                        <span class="text-muted">h1</span>
                    </div>
                    @forelse($selectedPage['h1s'] as $h1)
                        <div class="font-sans text-[13px] text-secondary leading-snug">{{ $h1 }}</div>
                    @empty
                        <div class="font-mono text-[12px] c-err">✗ missing h1</div>
                    @endforelse
                </div>

                <div class="flex gap-5 text-[11px] shrink-0 font-mono">
                    @foreach([
                        ['words',    $selectedPage['wordCount']],
                        ['internal', $selectedPage['internalLinkCount']],
                        ['external', $selectedPage['externalLinkCount']],
                    ] as [$metricLabel, $metricValue])
                    <div>
                        <div class="text-muted uppercase tracking-[0.14em] mb-0.5">{{ $metricLabel }}</div>
                        <div class="text-primary tabular-nums text-[14px]">{{ number_format($metricValue) }}</div>
                    </div>
                    @endforeach
                    <div>
                        <div class="text-muted uppercase tracking-[0.14em] mb-0.5">canonical</div>
                        @php $cs = $selectedPage['canonicalStatus']; @endphp
                        <div class="{{ match($cs) { 'self' => 'c-ok', 'other' => 'c-warn', default => 'c-err' } }}">
                            {{ match($cs) { 'self' => 'self-ref', 'other' => 'canon', default => 'missing' } }}
                        </div>
                    </div>
                </div>
            </div>

            @if($selectedPage['canonicalStatus'] === 'other' && $selectedPage['canonical'])
            <div>
                <div class="flex items-center gap-2 mb-1 font-mono text-[10px] uppercase tracking-[0.16em]">
                    <span class="c-accent">▸</span>
                    <span class="text-muted">canonical_target</span>
                </div>
                <div class="font-mono text-[11px] text-secondary truncate">{{ $selectedPage['canonical'] }}</div>
            </div>
            @endif

            @if(count($selectedPage['hreflangs']) > 0)
            <div>
                <div class="flex items-center gap-2 mb-1.5 font-mono text-[10px] uppercase tracking-[0.16em]">
                    <span class="c-accent">▸</span>
                    <span class="text-muted">hreflangs</span>
                </div>
                <div class="flex flex-wrap gap-1">
                    @foreach($selectedPage['hreflangs'] as $hl)
                        <span class="badge badge-info">{{ $hl['language'] }}{{ $hl['region'] ? '-'.$hl['region'] : '' }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- SERP PREVIEW — keep Google colors intentionally, framed as a file --}}
            @if($selectedPage['title'] || $selectedPage['metaDescription'])
            <div>
                <div class="flex items-center gap-2 mb-1.5 font-mono text-[10px] uppercase tracking-[0.16em]">
                    <span class="c-accent">▸</span>
                    <span class="text-muted">serp_preview</span>
                    <span class="text-tertiary">·</span>
                    <span class="text-muted">google.html</span>
                </div>
                <div class="bg-white border border-line2 p-3 max-w-[600px]" style="font-family: arial, sans-serif;">
                    <div class="text-[16px] leading-snug truncate" style="color:#1a0dab;">
                        {{ \Illuminate\Support\Str::limit($selectedPage['title'] ?? $selectedPage['url'], 60) }}
                    </div>
                    <div class="text-[13px] truncate mt-0.5" style="color:#006621;">
                        {{ $selectedPage['url'] }}
                    </div>
                    @if($selectedPage['metaDescription'])
                    <div class="text-[13px] leading-relaxed mt-0.5 line-clamp-2" style="color:#545454;">
                        {{ \Illuminate\Support\Str::limit($selectedPage['metaDescription'], 160) }}
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- ═══ TECHNICAL ═══ --}}
        @elseif($detailTab === 'technical')
        <div class="p-4 anim-fade">

            {{-- key=value grid --}}
            <div class="grid grid-cols-3 gap-0 border border-line bg-app2">
                @php
                    $techFields = [
                        ['content_type', $selectedPage['contentType'] ?: '—', null],
                        ['size',         number_format($selectedPage['bodySize']/1024, 1) . ' KB', null],
                        ['response_ms',  number_format($selectedPage['responseTime'], 0), $selectedPage['responseTime'] > 1000 ? 'var(--c-warn)' : null],
                        ['depth',        (string)$selectedPage['crawlDepth'], null],
                        ['links_int',    (string)$selectedPage['internalLinkCount'], null],
                        ['links_ext',    (string)$selectedPage['externalLinkCount'], null],
                    ];
                @endphp
                @foreach($techFields as $i => [$tLabel, $tVal, $tColor])
                <div class="px-3 py-2 {{ $i % 3 !== 2 ? 'border-r border-line' : '' }} {{ $i < 3 ? 'border-b border-line' : '' }}">
                    <div class="text-[10px] font-mono uppercase tracking-[0.16em] text-muted mb-0.5">{{ $tLabel }}</div>
                    <div class="font-mono text-[13px] tabular-nums" style="{{ $tColor ? 'color:'.$tColor : 'color: var(--c-fg)' }}">{{ $tVal }}</div>
                </div>
                @endforeach
            </div>

            {{-- Redirect chain --}}
            @if(count($selectedPage['redirectChain']) > 0)
            <div class="mt-4">
                <div class="flex items-center gap-2 mb-2 font-mono text-[10px] uppercase tracking-[0.16em]">
                    <span class="c-accent">▸</span>
                    <span class="text-muted">redirect_chain</span>
                    <span class="text-tertiary">·{{ count($selectedPage['redirectChain']) }}</span>
                </div>
                <div class="space-y-1">
                    @foreach($selectedPage['redirectChain'] as $hop)
                    <div class="flex items-center gap-2 text-[11px] font-mono">
                        <span class="badge badge-warn text-[9px]">{{ $hop['statusCode'] }}</span>
                        <span class="text-muted truncate">{{ \Illuminate\Support\Str::limit($hop['from'], 50) }}</span>
                        <span class="c-accent shrink-0">→</span>
                        <span class="text-secondary truncate">{{ \Illuminate\Support\Str::limit($hop['to'], 50) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- ═══ LINKS ═══ --}}
        @elseif($detailTab === 'links')
        <div class="anim-fade" x-data="{ linkTab: 'internal' }">

            {{-- Sub-tabs --}}
            <div class="flex items-center gap-0 px-3 pt-2 pb-1 border-b border-line">
                @foreach([
                    'internal' => ['internal', count($intLinks)],
                    'external' => ['outgoing', count($extLinks)],
                    'images'   => ['images',   count($imgLinks)],
                ] as $ltKey => [$ltLabel, $ltCount])
                <button @click="linkTab = '{{ $ltKey }}'"
                        class="h-6 px-2.5 flex items-center gap-1 font-mono text-[10px] uppercase tracking-[0.14em] transition-colors"
                        :class="linkTab === '{{ $ltKey }}'
                                ? 'c-accent'
                                : 'text-tertiary hover:text-secondary'">
                    <span class="text-muted" :class="linkTab === '{{ $ltKey }}' ? 'text-muted' : 'text-muted'">[</span>
                    <span class="flex items-center gap-1.5">
                        <template x-if="linkTab === '{{ $ltKey }}'">
                            <span class="c-accent">▸</span>
                        </template>
                        <span>{{ $ltLabel }}</span>
                        <span class="text-muted font-normal">·{{ $ltCount }}</span>
                    </span>
                    <span class="text-muted">]</span>
                </button>
                @endforeach
            </div>

            {{-- Internal links --}}
            <div x-show="linkTab === 'internal'" class="overflow-x-auto">
                @if(count($intLinks) > 0)
                <table class="w-full text-[11px] border-collapse font-mono" style="min-width:600px">
                    <thead>
                        <tr class="border-b border-line bg-panel2">
                            <th class="text-left px-3 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted">target_url</th>
                            <th class="text-left px-2 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted w-[180px]">anchor</th>
                            <th class="text-center px-2 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted w-16">rel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($intLinks as $link)
                        <tr class="border-b border-line row-hover">
                            <td class="px-3 py-[5px] text-secondary truncate max-w-0">{{ $link['url'] }}</td>
                            <td class="px-2 py-[5px] text-muted truncate max-w-0 font-sans">{{ $link['anchor'] ?? '—' }}</td>
                            <td class="px-2 py-[5px] text-center">
                                @if($link['relation'] !== 'follow')
                                    <span class="badge badge-warn">{{ $link['relation'] }}</span>
                                @else
                                    <span class="text-muted">follow</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="flex items-center justify-center py-6 font-mono text-[12px] text-muted">
                    <span class="c-accent mr-2">$</span>no internal links
                </div>
                @endif
            </div>

            {{-- External links --}}
            <div x-show="linkTab === 'external'" class="overflow-x-auto">
                @if(count($extLinks) > 0)
                <table class="w-full text-[11px] border-collapse font-mono" style="min-width:600px">
                    <thead>
                        <tr class="border-b border-line bg-panel2">
                            <th class="text-left px-3 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted">target_url</th>
                            <th class="text-left px-2 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted w-[180px]">anchor</th>
                            <th class="text-center px-2 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted w-16">rel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($extLinks as $link)
                        <tr class="border-b border-line row-hover">
                            <td class="px-3 py-[5px] text-secondary truncate max-w-0">{{ $link['url'] }}</td>
                            <td class="px-2 py-[5px] text-muted truncate max-w-0 font-sans">{{ $link['anchor'] ?? '—' }}</td>
                            <td class="px-2 py-[5px] text-center">
                                @if($link['relation'] !== 'follow')
                                    <span class="badge badge-warn">{{ $link['relation'] }}</span>
                                @else
                                    <span class="text-muted">follow</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="flex items-center justify-center py-6 font-mono text-[12px] text-muted">
                    <span class="c-accent mr-2">$</span>no outgoing links
                </div>
                @endif
            </div>

            {{-- Images --}}
            <div x-show="linkTab === 'images'" class="overflow-x-auto">
                @if(count($imgLinks) > 0)
                <table class="w-full text-[11px] border-collapse font-mono" style="min-width:600px">
                    <thead>
                        <tr class="border-b border-line bg-panel2">
                            <th class="text-left px-3 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted">image_url</th>
                            <th class="text-left px-2 py-1.5 text-[10px] uppercase tracking-[0.14em] text-muted w-[220px]">alt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($imgLinks as $link)
                        <tr class="border-b border-line row-hover">
                            <td class="px-3 py-[5px] text-secondary truncate max-w-0">{{ $link['url'] }}</td>
                            <td class="px-2 py-[5px] truncate max-w-0">
                                @if($link['anchor'])
                                    <span class="text-muted font-sans">{{ $link['anchor'] }}</span>
                                @else
                                    <span class="c-warn">✗ missing alt</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="flex items-center justify-center py-6 font-mono text-[12px] text-muted">
                    <span class="c-accent mr-2">$</span>no images
                </div>
                @endif
            </div>
        </div>

        {{-- ═══ ISSUES ═══ --}}
        @elseif($detailTab === 'issues')
        <div class="p-4 anim-fade">
            @if(count($selectedPage['issues']) > 0)
                @php
                    $grouped = [];
                    foreach ($selectedPage['issues'] as $issue) {
                        $grouped[$issue['category'] ?? 'other'][] = $issue;
                    }
                @endphp
                <div class="space-y-4">
                    @foreach($grouped as $cat => $catIssues)
                    <div>
                        <div class="flex items-center gap-2 mb-1.5 font-mono text-[10px] uppercase tracking-[0.16em]">
                            <span class="c-accent">▸</span>
                            <span class="text-muted">{{ $cat }}</span>
                            <span class="text-tertiary">·{{ count($catIssues) }}</span>
                        </div>
                        <div class="space-y-1">
                            @foreach($catIssues as $issue)
                            @php
                                $sev = $issue['severity'];
                                $sevColor = match($sev) {
                                    'error'   => 'var(--c-err)',
                                    'warning' => 'var(--c-warn)',
                                    'notice'  => 'var(--c-info)',
                                    default   => 'var(--c-fg4)',
                                };
                                $sevGlyph = match($sev) {
                                    'error'   => '●',
                                    'warning' => '▲',
                                    'notice'  => '●',
                                    default   => '○',
                                };
                                $sevBadge = match($sev) {
                                    'error'   => 'badge-err',
                                    'warning' => 'badge-warn',
                                    'notice'  => 'badge-info',
                                    default   => '',
                                };
                            @endphp
                            <div class="flex gap-2.5 text-[12px] bg-app2 border border-line px-3 py-2">
                                <span class="mt-[3px] font-mono shrink-0 text-[10px]" style="color: {{ $sevColor }};">{{ $sevGlyph }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-primary font-sans">{{ $issue['message'] }}</span>
                                        <span class="badge {{ $sevBadge }}">{{ $sev }}</span>
                                    </div>
                                    @if($issue['context'])
                                    <div class="text-[11px] text-muted font-mono mt-1 truncate">
                                        <span class="text-tertiary">└─ </span>{{ $issue['context'] }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center py-8 gap-2 font-mono text-[12px] c-ok">
                    <span>✓</span>
                    <span>no issues detected</span>
                </div>
            @endif
        </div>
        @endif
    </div>
</div>
@endif
