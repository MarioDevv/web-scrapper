<div class="h-screen flex flex-col" @if($crawling) wire:poll.1s="poll" @endif>

    {{-- ══════════ COMMAND BAR ══════════ --}}
    <header class="flex-none bg-surface border-b border-border px-3 py-2.5">
        <div class="flex items-center gap-2.5">
            {{-- Logo --}}
            <div class="flex items-center gap-2 pr-3 border-r border-border">
                <div class="w-7 h-7 rounded-lg bg-accent/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/>
                    </svg>
                </div>
                <span class="text-xs font-semibold text-fg-2 tracking-wide hidden xl:block">SEO SPIDER</span>
            </div>

            {{-- URL --}}
            <div class="flex-1 relative">
                <input type="url" wire:model="url" wire:keydown.enter="startCrawl"
                    placeholder="https://example.com"
                    class="w-full h-8 bg-bg-2 border border-border rounded-md pl-3 pr-10 text-[13px] font-mono text-fg placeholder:text-fg-4 focus:outline-none focus:border-accent focus:ring-1 focus:ring-accent/25 transition-colors"
                    @disabled($crawling) autocomplete="off" spellcheck="false">
                @if($crawling)
                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                    <div class="w-3.5 h-3.5 border-[1.5px] border-accent border-t-transparent rounded-full animate-spin"></div>
                </div>
                @endif
            </div>

            {{-- Compact config --}}
            <div class="flex items-center gap-1.5">
                <div class="flex items-center gap-1 bg-bg-2 border border-border rounded-md px-2 h-8">
                    <span class="text-2xs text-fg-4">Pages</span>
                    <input type="number" wire:model="maxPages" class="w-12 bg-transparent text-xs font-mono text-fg text-center focus:outline-none" @disabled($crawling)>
                </div>
                <div class="flex items-center gap-1 bg-bg-2 border border-border rounded-md px-2 h-8">
                    <span class="text-2xs text-fg-4">Depth</span>
                    <input type="number" wire:model="maxDepth" class="w-8 bg-transparent text-xs font-mono text-fg text-center focus:outline-none" @disabled($crawling)>
                </div>
            </div>

            {{-- Action --}}
            @if($crawling)
                <button wire:click="cancelCrawl" class="h-8 px-4 rounded-md text-xs font-medium bg-err/10 text-err border border-err/20 hover:bg-err/20 transition-colors">
                    Stop
                </button>
            @else
                <button wire:click="startCrawl" class="h-8 px-5 rounded-md text-xs font-semibold bg-accent text-white hover:bg-accent/90 transition-colors shadow-sm shadow-accent/25">
                    Crawl
                </button>
            @endif
        </div>

        @error('url') <p class="text-err text-2xs mt-1.5 pl-28">{{ $message }}</p> @enderror

        {{-- Progress --}}
        @if($status)
        <div class="mt-2 flex items-center gap-3">
            <div class="flex-1">
                <div class="w-full h-[3px] bg-bg-3 rounded-full overflow-hidden">
                    @php
                        $ref = max($status['pagesDiscovered'], $status['maxPages'], 1);
                        $pct = min(($status['pagesCrawled'] / $ref) * 100, 100);
                    @endphp
                    <div class="h-full rounded-full transition-all duration-700 ease-out
                        {{ $crawling ? 'bg-accent' : ($status['status'] === 'completed' ? 'bg-ok' : 'bg-err') }}"
                        style="width:{{ $pct }}%"></div>
                </div>
            </div>
            <div class="flex items-center gap-3 text-2xs text-fg-3 tabular-nums shrink-0">
                @if($crawling)
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-accent dot-pulse"></span> Crawling</span>
                @else
                    <span class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full {{ $status['status'] === 'completed' ? 'bg-ok' : 'bg-err' }}"></span>
                        {{ ucfirst($status['status']) }}
                    </span>
                @endif
                <span>{{ $status['pagesCrawled'] }} pages</span>
                @if($status['errorsFound'] > 0)<span class="text-err">{{ $status['errorsFound'] }}E</span>@endif
                @if($status['warningsFound'] > 0)<span class="text-warn">{{ $status['warningsFound'] }}W</span>@endif
                @if($status['duration'])<span>{{ number_format($status['duration'], 1) }}s</span>@endif
            </div>
        </div>
        @endif
    </header>

    {{-- ══════════ BODY ══════════ --}}
    <div class="flex-1 flex min-h-0">

        {{-- ──── SIDEBAR ──── --}}
        <aside class="flex-none w-52 bg-surface border-r border-border flex flex-col min-h-0">
            {{-- Stats --}}
            @if($status)
            <div class="flex-none p-3 border-b border-border">
                <div class="grid grid-cols-2 gap-1.5">
                    @foreach([
                        ['Pages', $status['pagesCrawled'], null],
                        ['Failed', $status['pagesFailed'], $status['pagesFailed'] > 0 ? 'text-err' : null],
                        ['Errors', $status['errorsFound'], $status['errorsFound'] > 0 ? 'text-err' : null],
                        ['Warnings', $status['warningsFound'], $status['warningsFound'] > 0 ? 'text-warn' : null],
                    ] as [$label, $value, $color])
                    <div class="bg-bg-2 rounded-md px-2.5 py-2">
                        <div class="text-2xs text-fg-4 leading-none">{{ $label }}</div>
                        <div class="text-sm font-semibold mt-1 leading-none {{ $color }}">{{ $value }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- History --}}
            <div class="flex-1 overflow-y-auto">
                <div class="px-3 pt-3 pb-1">
                    <h3 class="text-2xs uppercase tracking-widest text-fg-4 font-semibold">History</h3>
                </div>
                <div class="px-2 pb-2 space-y-0.5">
                    @forelse($auditHistory as $audit)
                    <button wire:click="loadAudit('{{ $audit['id'] }}')"
                        class="w-full text-left px-2.5 py-2 rounded-md transition-colors
                            {{ $auditId === $audit['id']
                                ? 'bg-accent/8 ring-1 ring-accent/20'
                                : 'hover:bg-surface-3' }}">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0
                                {{ match($audit['status']) {
                                    'completed' => 'bg-ok',
                                    'running' => 'bg-accent dot-pulse',
                                    'failed' => 'bg-err',
                                    default => 'bg-fg-4'
                                } }}"></span>
                            <span class="text-xs font-mono truncate {{ $auditId === $audit['id'] ? 'text-fg' : 'text-fg-3' }}">
                                {{ parse_url($audit['seed_url'], PHP_URL_HOST) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-1 pl-3 text-2xs text-fg-4">
                            <span>{{ $audit['pages_crawled'] }}p</span>
                            @if($audit['errors_found'] > 0)<span class="text-err">{{ $audit['errors_found'] }}E</span>@endif
                            @if($audit['warnings_found'] > 0)<span class="text-warn">{{ $audit['warnings_found'] }}W</span>@endif
                        </div>
                    </button>
                    @empty
                    <p class="text-2xs text-fg-4 italic px-2.5 py-4">No audits yet. Enter a URL above.</p>
                    @endforelse
                </div>
            </div>
        </aside>

        {{-- ──── MAIN ──── --}}
        <main class="flex-1 flex flex-col min-h-0 min-w-0">

            {{-- Tabs --}}
            <nav class="flex-none flex items-center bg-surface-2 border-b border-border">
                @php
                    $tabs = [
                        'all'       => ['All',       count($pages)],
                        'html'      => ['HTML',      count(array_filter($pages, fn($p) => str_contains($p['contentType'] ?? '', 'html')))],
                        'redirects' => ['3xx',        count(array_filter($pages, fn($p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400))],
                        'errors'    => ['4xx/5xx',    count(array_filter($pages, fn($p) => $p['statusCode'] >= 400))],
                        'issues'    => ['Issues',    count(array_filter($pages, fn($p) => $p['errorCount'] > 0 || $p['warningCount'] > 0))],
                    ];
                @endphp
                @foreach($tabs as $key => [$label, $count])
                <button wire:click="setTab('{{ $key }}')"
                    class="relative px-4 py-2 text-xs font-medium transition-colors
                        {{ $activeTab === $key ? 'text-fg' : 'text-fg-4 hover:text-fg-3' }}">
                    {{ $label }}
                    @if($count > 0)
                        <span class="ml-1 text-2xs px-1 py-0.5 rounded
                            {{ $activeTab === $key ? 'bg-accent/15 text-accent' : 'bg-surface-3 text-fg-4' }}">{{ $count }}</span>
                    @endif
                    @if($activeTab === $key)
                        <span class="absolute bottom-0 left-2 right-2 h-[2px] bg-accent rounded-full"></span>
                    @endif
                </button>
                @endforeach
            </nav>

            {{-- Table --}}
            <div class="flex-1 overflow-auto min-h-0">
                @if(count($this->filteredPages) > 0)
                <table class="w-full text-xs border-collapse">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-surface-2 border-b border-border">
                            <th class="text-left  px-3 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider">URL</th>
                            <th class="text-center px-2 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider w-14">Code</th>
                            <th class="text-left  px-2 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider">Title</th>
                            <th class="text-right px-2 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider w-16">Size</th>
                            <th class="text-right px-2 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider w-16">Time</th>
                            <th class="text-center px-2 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider w-12">Lvl</th>
                            <th class="text-center px-2 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider w-16">Issues</th>
                            <th class="text-center px-2 py-2 font-medium text-fg-4 text-2xs uppercase tracking-wider w-10">Idx</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        @foreach($this->filteredPages as $page)
                        <tr wire:click="selectPage('{{ $page['pageId'] }}')"
                            class="cursor-pointer transition-colors {{ $selectedPageId === $page['pageId'] ? 'row-selected' : '' }}">
                            <td class="px-3 py-[7px] font-mono text-[11px] truncate max-w-0">
                                <span class="text-fg-4">{{ parse_url($page['url'], PHP_URL_HOST) }}</span><span class="text-fg-2">{{ parse_url($page['url'], PHP_URL_PATH) ?: '/' }}</span>
                            </td>
                            <td class="px-2 py-[7px] text-center">
                                @php $sc = $page['statusCode']; @endphp
                                <span class="inline-block min-w-[32px] px-1 py-[1px] rounded font-mono text-[10px] font-medium text-center
                                    {{ $sc >= 200 && $sc < 300 ? 'bg-ok/10 text-ok' :
                                       ($sc >= 300 && $sc < 400 ? 'bg-warn/10 text-warn' : 'bg-err/10 text-err') }}">{{ $sc }}</span>
                            </td>
                            <td class="px-2 py-[7px] text-fg-2 truncate max-w-0">{{ $page['title'] ?? '' }}</td>
                            <td class="px-2 py-[7px] text-right font-mono text-fg-4 text-[11px]">
                                {{ $page['bodySize'] > 1048576
                                    ? number_format($page['bodySize']/1048576,1).'M'
                                    : ($page['bodySize'] > 1024
                                        ? number_format($page['bodySize']/1024,0).'K'
                                        : $page['bodySize'].'B') }}
                            </td>
                            <td class="px-2 py-[7px] text-right font-mono text-[11px] {{ $page['responseTime'] > 1000 ? 'text-warn' : 'text-fg-4' }}">
                                {{ number_format($page['responseTime'], 0) }}<span class="text-fg-4/60">ms</span>
                            </td>
                            <td class="px-2 py-[7px] text-center text-fg-4">{{ $page['crawlDepth'] }}</td>
                            <td class="px-2 py-[7px] text-center">
                                @if($page['errorCount'] > 0)<span class="text-err font-medium">{{ $page['errorCount'] }}E</span>@endif
                                @if($page['warningCount'] > 0)<span class="text-warn font-medium {{ $page['errorCount'] > 0 ? 'ml-0.5' : '' }}">{{ $page['warningCount'] }}W</span>@endif
                                @if($page['errorCount'] === 0 && $page['warningCount'] === 0)<span class="text-ok">✓</span>@endif
                            </td>
                            <td class="px-2 py-[7px] text-center">
                                <span class="w-2 h-2 rounded-full inline-block {{ $page['isIndexable'] ? 'bg-ok' : 'bg-err/60' }}"></span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @elseif($auditId)
                <div class="flex items-center justify-center h-full text-fg-4 text-sm">
                    No pages match this filter
                </div>
                @else
                <div class="flex flex-col items-center justify-center h-full gap-3">
                    <div class="w-12 h-12 rounded-xl bg-surface-2 flex items-center justify-center">
                        <svg class="w-6 h-6 text-fg-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/>
                        </svg>
                    </div>
                    <p class="text-sm text-fg-4">Enter a URL and click <span class="text-accent font-medium">Crawl</span> to start</p>
                </div>
                @endif
            </div>

            {{-- ──── DETAIL PANEL ──── --}}
            @if($detailOpen && $selectedPage)
            <div class="flex-none border-t border-border bg-surface" style="height:220px">
                {{-- Header --}}
                <div class="flex items-center justify-between px-3 h-8 border-b border-border bg-surface-2">
                    <div class="flex items-center gap-2 min-w-0">
                        @php $sc = $selectedPage['statusCode']; @endphp
                        <span class="font-mono text-[10px] font-medium px-1 py-[1px] rounded
                            {{ $sc >= 200 && $sc < 300 ? 'bg-ok/10 text-ok' :
                               ($sc >= 300 && $sc < 400 ? 'bg-warn/10 text-warn' : 'bg-err/10 text-err') }}">{{ $sc }}</span>
                        <span class="font-mono text-[11px] text-fg-2 truncate">{{ $selectedPage['url'] }}</span>
                    </div>
                    <button wire:click="closeDetail" class="text-fg-4 hover:text-fg-2 transition-colors p-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Content --}}
                <div class="grid grid-cols-3 gap-0 divide-x divide-border h-[calc(220px-32px)] overflow-hidden">

                    {{-- Col 1: SEO --}}
                    <div class="p-3 overflow-y-auto space-y-2.5">
                        <h4 class="text-2xs uppercase tracking-widest text-fg-4 font-semibold">SEO</h4>

                        <div>
                            <div class="text-2xs text-fg-4 mb-0.5">Title</div>
                            <div class="text-xs text-fg-2">{{ $selectedPage['title'] ?? '—' }}</div>
                            @if($selectedPage['titleLength'])
                            <div class="text-2xs mt-0.5 {{ $selectedPage['titleLength'] > 60 || $selectedPage['titleLength'] < 30 ? 'text-warn' : 'text-fg-4' }}">
                                {{ $selectedPage['titleLength'] }} characters
                            </div>
                            @endif
                        </div>

                        <div>
                            <div class="text-2xs text-fg-4 mb-0.5">Meta Description</div>
                            <div class="text-[11px] text-fg-3 leading-relaxed">{{ \Illuminate\Support\Str::limit($selectedPage['metaDescription'] ?? '—', 120) }}</div>
                            @if($selectedPage['metaDescriptionLength'])
                            <div class="text-2xs mt-0.5 {{ $selectedPage['metaDescriptionLength'] > 160 ? 'text-warn' : 'text-fg-4' }}">
                                {{ $selectedPage['metaDescriptionLength'] }} characters
                            </div>
                            @endif
                        </div>

                        <div>
                            <div class="text-2xs text-fg-4 mb-0.5">H1</div>
                            @forelse($selectedPage['h1s'] as $h1)
                                <div class="text-xs text-fg-2">{{ $h1 }}</div>
                            @empty
                                <div class="text-xs text-err">Missing</div>
                            @endforelse
                        </div>

                        <div class="flex gap-4 text-2xs">
                            <div><span class="text-fg-4">Words:</span> <span class="text-fg-2">{{ $selectedPage['wordCount'] }}</span></div>
                            <div><span class="text-fg-4">Canonical:</span> <span class="text-fg-3 font-mono">{{ $selectedPage['canonical'] ? 'Yes' : 'No' }}</span></div>
                        </div>
                    </div>

                    {{-- Col 2: Technical --}}
                    <div class="p-3 overflow-y-auto space-y-2.5">
                        <h4 class="text-2xs uppercase tracking-widest text-fg-4 font-semibold">Technical</h4>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-2xs">
                            <div><span class="text-fg-4">Content-Type</span></div>
                            <div class="font-mono text-fg-3">{{ $selectedPage['contentType'] }}</div>

                            <div><span class="text-fg-4">Size</span></div>
                            <div class="font-mono text-fg-3">{{ number_format($selectedPage['bodySize']/1024, 1) }} KB</div>

                            <div><span class="text-fg-4">Response</span></div>
                            <div class="font-mono {{ $selectedPage['responseTime'] > 1000 ? 'text-warn' : 'text-fg-3' }}">{{ number_format($selectedPage['responseTime'], 0) }}ms</div>

                            <div><span class="text-fg-4">Depth</span></div>
                            <div class="text-fg-3">{{ $selectedPage['crawlDepth'] }}</div>

                            <div><span class="text-fg-4">Internal links</span></div>
                            <div class="text-fg-3">{{ $selectedPage['internalLinkCount'] }}</div>

                            <div><span class="text-fg-4">External links</span></div>
                            <div class="text-fg-3">{{ $selectedPage['externalLinkCount'] }}</div>
                        </div>

                        <div class="flex flex-wrap gap-1 pt-1">
                            @if($selectedPage['isIndexable'])<span class="text-2xs px-1.5 py-0.5 rounded-sm bg-ok/10 text-ok font-medium">Indexable</span>@endif
                            @if($selectedPage['noindex'])<span class="text-2xs px-1.5 py-0.5 rounded-sm bg-err/10 text-err font-medium">noindex</span>@endif
                            @if($selectedPage['nofollow'])<span class="text-2xs px-1.5 py-0.5 rounded-sm bg-warn/10 text-warn font-medium">nofollow</span>@endif
                        </div>

                        @if(count($selectedPage['redirectChain']) > 0)
                        <div class="pt-1">
                            <div class="text-2xs text-fg-4 mb-1">Redirect Chain</div>
                            @foreach($selectedPage['redirectChain'] as $hop)
                            <div class="text-[10px] font-mono text-fg-4">
                                <span class="text-warn">{{ $hop['statusCode'] }}</span> {{ \Illuminate\Support\Str::limit($hop['from'], 40) }} → {{ \Illuminate\Support\Str::limit($hop['to'], 40) }}
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Col 3: Issues --}}
                    <div class="p-3 overflow-y-auto">
                        <h4 class="text-2xs uppercase tracking-widest text-fg-4 font-semibold mb-2">
                            Issues <span class="text-fg-4/50 font-normal">({{ count($selectedPage['issues']) }})</span>
                        </h4>
                        <div class="space-y-1.5">
                            @forelse($selectedPage['issues'] as $issue)
                            <div class="flex gap-2 text-[11px]">
                                <span class="mt-[5px] w-1.5 h-1.5 rounded-full shrink-0
                                    {{ match($issue['severity']) {
                                        'error' => 'bg-err',
                                        'warning' => 'bg-warn',
                                        default => 'bg-fg-4'
                                    } }}"></span>
                                <div class="min-w-0">
                                    <div class="text-fg-2">{{ $issue['message'] }}</div>
                                    @if($issue['context'])
                                    <div class="text-[10px] text-fg-4 font-mono mt-0.5 truncate">{{ $issue['context'] }}</div>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <p class="text-2xs text-fg-4 italic">No issues detected</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ──── STATUS BAR ──── --}}
            <footer class="flex-none h-6 bg-surface-2 border-t border-border px-3 flex items-center justify-between text-2xs text-fg-4 tabular-nums">
                <span>{{ count($this->filteredPages) }} pages @if($activeTab !== 'all') <span class="text-fg-4/50">({{ count($pages) }} total)</span> @endif</span>
                <span>
                    @if($status)
                        {{ parse_url($status['seedUrl'] ?? '', PHP_URL_HOST) }}
                    @else
                        Ready
                    @endif
                </span>
            </footer>
        </main>
    </div>
</div>
