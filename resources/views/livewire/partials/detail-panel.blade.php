{{-- ══ DETAIL PANEL ══ --}}
@if($detailOpen && $selectedPage)
<div class="flex-none border-t border-line bg-panel anim-slide" style="height:280px">

    {{-- Header --}}
    <div class="flex items-center justify-between px-3 h-9 border-b border-line bg-panel2">
        <div class="flex items-center gap-2.5 min-w-0">
            @php $sc = $selectedPage['statusCode']; @endphp
            <span class="badge font-mono text-[11px] {{ $sc >= 200 && $sc < 300 ? 'badge-ok' : ($sc >= 300 && $sc < 400 ? 'badge-warn' : 'badge-err') }}">{{ $sc }}</span>
            <span class="font-mono text-[12px] text-secondary truncate">{{ $selectedPage['url'] }}</span>
            <div class="flex items-center gap-1 shrink-0">
                @if($selectedPage['isIndexable'])<span class="badge badge-ok">Indexable</span>@endif
                @if($selectedPage['noindex'])<span class="badge badge-err">noindex</span>@endif
                @if($selectedPage['nofollow'])<span class="badge badge-warn">nofollow</span>@endif
            </div>
        </div>
        <button wire:click="closeDetail" class="text-muted hover:text-secondary transition-colors p-1.5 rounded-md hover:bg-panel3">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Detail tabs --}}
    <div class="flex items-center border-b border-line bg-panel px-1">
        @foreach(['seo' => 'SEO', 'technical' => 'Technical', 'issues' => 'Issues (' . count($selectedPage['issues']) . ')'] as $tKey => $tLabel)
        <button wire:click="setDetailTab('{{ $tKey }}')"
            class="relative px-3 py-1.5 text-2xs font-medium transition-colors
                {{ $detailTab === $tKey ? 'text-primary' : 'text-tertiary hover:text-secondary' }}">
            {{ $tLabel }}
            @if($detailTab === $tKey)
                <span class="absolute bottom-0 left-1 right-1 h-[1.5px] rounded-full" style="background:var(--c-accent)"></span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Content --}}
    <div class="h-[calc(280px-36px-30px)] overflow-y-auto">

        @if($detailTab === 'seo')
        <div class="p-4 space-y-4 anim-fade">
            {{-- Title --}}
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-2xs text-tertiary font-semibold uppercase tracking-wider">Title</span>
                    @if($selectedPage['titleLength'])
                        @php $tl = $selectedPage['titleLength']; @endphp
                        <span class="text-2xs" style="color:{{ $tl > 60 || $tl < 30 ? 'var(--c-warn)' : 'var(--c-ok)' }}">{{ $tl }} chars</span>
                        <div class="flex-1 h-1 bg-app3 rounded-full max-w-[140px] overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ min(($tl / 70) * 100, 100) }}%;background:{{ $tl > 60 || $tl < 30 ? 'var(--c-warn)' : 'var(--c-ok)' }}"></div>
                        </div>
                    @endif
                </div>
                <div class="text-[13px] text-primary">{{ $selectedPage['title'] ?? '—' }}</div>
            </div>

            {{-- Meta description --}}
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-2xs text-tertiary font-semibold uppercase tracking-wider">Meta Description</span>
                    @if($selectedPage['metaDescriptionLength'])
                        @php $ml = $selectedPage['metaDescriptionLength']; @endphp
                        <span class="text-2xs" style="color:{{ $ml > 160 || $ml < 70 ? 'var(--c-warn)' : 'var(--c-ok)' }}">{{ $ml }} chars</span>
                        <div class="flex-1 h-1 bg-app3 rounded-full max-w-[140px] overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ min(($ml / 170) * 100, 100) }}%;background:{{ $ml > 160 || $ml < 70 ? 'var(--c-warn)' : 'var(--c-ok)' }}"></div>
                        </div>
                    @endif
                </div>
                <div class="text-[12px] text-secondary leading-relaxed">{{ \Illuminate\Support\Str::limit($selectedPage['metaDescription'] ?? '—', 220) }}</div>
            </div>

            {{-- H1 + Metrics --}}
            <div class="flex gap-6">
                <div class="flex-1">
                    <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-1">H1</div>
                    @forelse($selectedPage['h1s'] as $h1)
                        <div class="text-[13px] text-secondary">{{ $h1 }}</div>
                    @empty
                        <div class="text-[13px] c-err font-medium">Missing H1</div>
                    @endforelse
                </div>
                <div class="flex gap-5 text-2xs shrink-0">
                    @foreach([
                        ['Words', $selectedPage['wordCount']],
                        ['Internal', $selectedPage['internalLinkCount']],
                        ['External', $selectedPage['externalLinkCount']],
                    ] as [$metricLabel, $metricValue])
                    <div>
                        <div class="text-tertiary mb-0.5">{{ $metricLabel }}</div>
                        <div class="text-primary font-medium tabular-nums">{{ $metricValue }}</div>
                    </div>
                    @endforeach
                    <div>
                        <div class="text-tertiary mb-0.5">Canonical</div>
                        @php $cs = $selectedPage['canonicalStatus']; @endphp
                        <div class="font-medium {{ match($cs) { 'self' => 'c-ok', 'other' => 'c-warn', default => 'c-err' } }}">
                            {{ match($cs) { 'self' => 'Self', 'other' => 'Other', default => 'Missing' } }}
                        </div>
                    </div>
                </div>
            </div>

            @if($selectedPage['canonicalStatus'] === 'other' && $selectedPage['canonical'])
            <div>
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-1">Canonical Target</div>
                <div class="text-[12px] font-mono text-secondary truncate">{{ $selectedPage['canonical'] }}</div>
            </div>
            @endif

            @if(count($selectedPage['hreflangs']) > 0)
            <div>
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-1">Hreflangs</div>
                <div class="flex flex-wrap gap-1">
                    @foreach($selectedPage['hreflangs'] as $hl)
                        <span class="badge badge-info">{{ $hl['language'] }}{{ $hl['region'] ? '-'.$hl['region'] : '' }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        @elseif($detailTab === 'technical')
        <div class="p-4 anim-fade">
            <div class="grid grid-cols-3 gap-3">
                @foreach([
                    ['Content-Type', $selectedPage['contentType'], null],
                    ['Body Size', number_format($selectedPage['bodySize']/1024, 1) . ' KB', null],
                    ['Response Time', number_format($selectedPage['responseTime'], 0) . 'ms', $selectedPage['responseTime'] > 1000 ? 'var(--c-warn)' : null],
                    ['Crawl Depth', (string)$selectedPage['crawlDepth'], null],
                    ['Internal Links', (string)$selectedPage['internalLinkCount'], null],
                    ['External Links', (string)$selectedPage['externalLinkCount'], null],
                ] as [$tLabel, $tVal, $tColor])
                <div class="bg-app2 rounded-lg px-3 py-2 border border-line">
                    <div class="text-2xs text-tertiary mb-0.5">{{ $tLabel }}</div>
                    <div class="text-[13px] font-mono" style="{{ $tColor ? "color:{$tColor}" : 'color:var(--c-fg2)' }}">{{ $tVal }}</div>
                </div>
                @endforeach
            </div>

            @if(count($selectedPage['redirectChain']) > 0)
            <div class="mt-3">
                <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-2">Redirect Chain</div>
                @foreach($selectedPage['redirectChain'] as $hop)
                <div class="flex items-center gap-2 text-[11px] font-mono mb-1">
                    <span class="badge badge-warn">{{ $hop['statusCode'] }}</span>
                    <span class="text-muted truncate">{{ \Illuminate\Support\Str::limit($hop['from'], 50) }}</span>
                    <svg class="w-3 h-3 text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 7l5 5-5 5m5-5H6"/></svg>
                    <span class="text-secondary truncate">{{ \Illuminate\Support\Str::limit($hop['to'], 50) }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

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
                        <div class="text-2xs text-tertiary font-semibold uppercase tracking-wider mb-1.5">
                            {{ ucfirst($cat) }} <span class="text-muted font-normal">({{ count($catIssues) }})</span>
                        </div>
                        <div class="space-y-1">
                            @foreach($catIssues as $issue)
                            <div class="flex gap-2.5 text-[12px] bg-app2 rounded-lg px-3 py-2 border border-line">
                                <span class="mt-[4px] w-2 h-2 rounded-full shrink-0"
                                    style="background:{{ match($issue['severity']) {
                                        'error' => 'var(--c-err)', 'warning' => 'var(--c-warn)',
                                        'notice' => 'var(--c-info)', default => 'var(--c-fg4)'
                                    } }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-primary">{{ $issue['message'] }}</span>
                                        <span class="badge {{ match($issue['severity']) {
                                            'error' => 'badge-err', 'warning' => 'badge-warn',
                                            'notice' => 'badge-info', default => ''
                                        } }}">{{ $issue['severity'] }}</span>
                                    </div>
                                    @if($issue['context'])
                                    <div class="text-[11px] text-muted font-mono mt-0.5 truncate">{{ $issue['context'] }}</div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center py-8 gap-2 text-tertiary">
                    <svg class="w-4 h-4" style="color:var(--c-ok);opacity:0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-[13px]">No issues detected</span>
                </div>
            @endif
        </div>
        @endif
    </div>
</div>
@endif
