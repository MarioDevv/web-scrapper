{{-- ══ TAB BAR + SEARCH ══ --}}
<nav class="flex-none flex items-center bg-panel2 border-b border-line gap-0.5 px-1">
    @php
        $tabs = [
            'all'       => ['All',     $this->tabCounts['all']],
            'html'      => ['HTML',    $this->tabCounts['html']],
            'redirects' => ['3xx',     $this->tabCounts['redirects']],
            'errors'    => ['4xx/5xx', $this->tabCounts['errors']],
            'issues'    => ['Issues',  $this->tabCounts['issues']],
            'noindex'   => ['Noindex', $this->tabCounts['noindex']],
        ];
    @endphp
    @foreach($tabs as $key => [$label, $count])
    <button wire:click="setTab('{{ $key }}')"
        class="relative px-3 py-2 text-[13px] font-medium transition-all duration-100
            {{ $activeTab === $key ? 'text-primary' : 'text-tertiary hover:text-secondary' }}">
        {{ $label }}
        @if($count > 0)
            <span class="ml-1 text-2xs tabular-nums px-1.5 py-[1px] rounded
                {{ $activeTab === $key ? 'bg-accent-s c-accent' : 'bg-panel3 text-tertiary' }}">{{ $count }}</span>
        @endif
        @if($activeTab === $key)
            <span class="absolute bottom-0 left-2 right-2 h-[2px] rounded-full" style="background:var(--c-accent)"></span>
        @endif
    </button>
    @endforeach

    {{-- Search --}}
    <div class="ml-auto flex items-center pr-2">
        <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
            <input type="text" wire:model.live.debounce.300ms="searchQuery"
                placeholder="Filter pages…"
                class="h-7 w-40 bg-app2 border border-line rounded-md pl-7 pr-2 text-2xs text-primary placeholder:text-muted focus:border-[var(--c-accent)] focus:w-56 transition-all duration-200">
        </div>
    </div>
</nav>

{{-- ══ TABLE ══ --}}
<div class="flex-1 overflow-auto min-h-0">
    @if(count($this->filteredPages) > 0)
    <table class="w-full text-[13px] border-collapse">
        <thead class="sticky top-0 z-10">
            <tr class="bg-panel2 border-b border-line" style="backdrop-filter:blur(8px)">
                @php
                    $cols = [
                        ['url',          'URL',    'text-left',   'px-3', ''],
                        ['statusCode',   'Status', 'text-center', 'px-2', 'w-16'],
                        ['title',        'Title',  'text-left',   'px-2', ''],
                        ['bodySize',     'Size',   'text-right',  'px-2', 'w-16'],
                        ['responseTime', 'Time',   'text-right',  'px-2', 'w-16'],
                        ['crawlDepth',   'Depth',  'text-center', 'px-2', 'w-12'],
                        ['errorCount',   'Issues', 'text-center', 'px-2', 'w-16'],
                        ['isIndexable',  'Idx',    'text-center', 'px-2', 'w-10'],
                    ];
                @endphp
                @foreach($cols as [$field, $label, $align, $px, $w])
                <th wire:click="toggleSort('{{ $field }}')"
                    class="{{ $align }} {{ $px }} py-2 font-medium text-tertiary text-2xs uppercase tracking-wider {{ $w }} cursor-pointer hover:text-secondary select-none transition-colors group">
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
            @foreach($this->filteredPages as $page)
            <tr wire:click="selectPage('{{ $page['pageId'] }}')"
                wire:key="page-{{ $page['pageId'] }}"
                class="cursor-pointer row-hover border-b border-line
                       {{ $selectedPageId === $page['pageId'] ? 'row-selected' : '' }}
                       {{ in_array($page['pageId'], $newPageIds) ? 'anim-fade' : '' }}">

                <td class="px-3 py-[7px] font-mono text-[12px] truncate max-w-0">
                    <span class="text-muted">{{ parse_url($page['url'], PHP_URL_HOST) }}</span><span class="text-secondary">{{ parse_url($page['url'], PHP_URL_PATH) ?: '/' }}</span>
                </td>

                <td class="px-2 py-[7px] text-center">
                    @php $sc = $page['statusCode']; @endphp
                    <span class="badge font-mono text-[11px]
                        {{ $sc >= 200 && $sc < 300 ? 'badge-ok' : ($sc >= 300 && $sc < 400 ? 'badge-warn' : 'badge-err') }}">{{ $sc }}</span>
                </td>

                <td class="px-2 py-[7px] text-secondary truncate max-w-0">{{ $page['title'] ?? '' }}</td>

                <td class="px-2 py-[7px] text-right font-mono text-[12px] text-tertiary tabular-nums">
                    {{ $page['bodySize'] > 1048576 ? number_format($page['bodySize']/1048576,1).'M'
                        : ($page['bodySize'] > 1024 ? number_format($page['bodySize']/1024,0).'K' : $page['bodySize'].'B') }}
                </td>

                <td class="px-2 py-[7px] text-right font-mono text-[12px] tabular-nums"
                    style="color:{{ $page['responseTime'] > 2000 ? 'var(--c-err)' : ($page['responseTime'] > 1000 ? 'var(--c-warn)' : 'var(--c-fg3)') }}">
                    {{ number_format($page['responseTime'], 0) }}<span class="text-muted">ms</span>
                </td>

                <td class="px-2 py-[7px] text-center text-tertiary tabular-nums">{{ $page['crawlDepth'] }}</td>

                <td class="px-2 py-[7px] text-center">
                    @if($page['errorCount'] > 0)<span class="c-err font-medium text-2xs">{{ $page['errorCount'] }}E</span>@endif
                    @if($page['warningCount'] > 0)<span class="c-warn font-medium text-2xs {{ $page['errorCount'] > 0 ? 'ml-0.5' : '' }}">{{ $page['warningCount'] }}W</span>@endif
                    @if($page['errorCount'] === 0 && $page['warningCount'] === 0)
                        <svg class="w-3.5 h-3.5 mx-auto" style="color:var(--c-ok);opacity:0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                    @endif
                </td>

                <td class="px-2 py-[7px] text-center">
                    <span class="w-2.5 h-2.5 rounded-full inline-block"
                        style="background:{{ $page['isIndexable'] ? 'var(--c-ok)' : 'var(--c-err)' }};opacity:{{ $page['isIndexable'] ? '0.6' : '0.4' }}"></span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @elseif($auditId && $crawling)
    {{-- Discovering --}}
    <div class="flex flex-col items-center justify-center h-full gap-4">
        <div class="w-12 h-12 rounded-xl bg-accent-s flex items-center justify-center">
            <div class="w-6 h-6 border-2 border-line rounded-full animate-spin" style="border-top-color:var(--c-accent)"></div>
        </div>
        <div class="text-center">
            <p class="text-[14px] text-secondary font-medium">Discovering pages…</p>
            <p class="text-2xs text-muted mt-1">Pages will appear here as they are crawled</p>
        </div>
    </div>

    @elseif($auditId)
    <div class="flex items-center justify-center h-full text-tertiary text-[14px]">No pages match this filter</div>

    @else
    {{-- Empty state --}}
    <div class="flex flex-col items-center justify-center h-full gap-4">
        <div class="w-16 h-16 rounded-2xl bg-panel2 flex items-center justify-center border border-line">
            <svg class="w-8 h-8 text-muted opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="6"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                <path d="M11 8v6m-3-3h6" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="text-center">
            <p class="text-[14px] text-secondary">Enter a URL and click <span class="c-accent font-semibold">Crawl</span> to start</p>
            <p class="text-2xs text-muted mt-1">Pages will appear in real-time as they are discovered</p>
        </div>
    </div>
    @endif
</div>
