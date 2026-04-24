{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  AUDIT REPORT — site-wide issues grouped by rule                           --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@php
    $report = $this->auditReport;
    $severityClass = [
        'error'   => 'c-err',
        'warning' => 'c-warn',
        'notice'  => 'c-accent',
        'info'    => 'text-tertiary',
    ];
    $severityBadge = [
        'error'   => 'badge-err',
        'warning' => 'badge-warn',
        'notice'  => 'badge-info',
        'info'    => 'badge-muted',
    ];
@endphp

<div class="flex-1 overflow-auto min-h-0">

    @if($auditId === null)
        <div class="p-8 font-mono text-[11px] text-tertiary text-center">
            No audit selected. Run or open one from the sidebar.
        </div>
    @elseif($report['totalIssues'] === 0)
        <div class="p-8 font-mono text-[11px] text-tertiary text-center">
            <div class="c-ok text-[13px] mb-2">✓ No issues detected</div>
            <div class="text-muted">Either no pages have been analyzed yet, or the site passes every active rule.</div>
        </div>
    @else
    <div class="p-5 space-y-5 max-w-5xl">

        {{-- ── HEADLINE ──────────────────────────────────────────── --}}
        <section class="bg-app2 border border-line">
            <header class="h-7 px-3 flex items-center justify-between border-b border-line text-[10px] font-mono uppercase tracking-[0.14em]">
                <span class="text-muted">audit · site-wide</span>
                <span class="text-tertiary">{{ count($report['groups']) }} rules triggered</span>
            </header>
            <div class="p-4 font-mono text-[11px] leading-relaxed">
                <div class="flex items-baseline gap-3 mb-3">
                    <span class="text-[22px] tabular-nums text-primary font-semibold">{{ $report['totalIssues'] }}</span>
                    <span class="text-muted uppercase tracking-[0.14em] text-[10px]">issues</span>
                    <span class="text-muted">·</span>
                    <span class="text-[14px] tabular-nums text-primary">{{ $report['affectedPages'] }}</span>
                    <span class="text-muted uppercase tracking-[0.14em] text-[10px]">pages affected</span>
                </div>

                {{-- Severity bar --}}
                <div class="flex items-center gap-3 mb-1">
                    @foreach(['error', 'warning', 'notice', 'info'] as $sev)
                        <span class="flex items-center gap-1.5">
                            <span class="{{ $severityClass[$sev] }} tabular-nums">{{ $report['severityTotals'][$sev] ?? 0 }}</span>
                            <span class="text-muted text-[10px] uppercase tracking-[0.14em]">{{ $sev }}</span>
                        </span>
                        @if(!$loop->last)
                            <span class="text-muted">·</span>
                        @endif
                    @endforeach
                </div>

                {{-- Category breakdown --}}
                @if(count($report['categoryTotals']) > 0)
                    <div class="flex items-center gap-3 flex-wrap text-[10px] pt-2 border-t border-line mt-2">
                        <span class="text-muted uppercase tracking-[0.14em]">by category</span>
                        @foreach($report['categoryTotals'] as $cat => $n)
                            <span>
                                <span class="text-tertiary">{{ $cat }}</span>
                                <span class="text-muted">·</span>
                                <span class="tabular-nums text-secondary">{{ $n }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- ── GROUPS ────────────────────────────────────────────── --}}
        @foreach($report['groups'] as $group)
            @php
                $sev = $group['severity'];
                $hasProse = $group['title'] !== null;
            @endphp
            <section class="bg-app2 border border-line">
                {{-- Group header --}}
                <header class="flex items-center justify-between px-3 h-9 border-b border-line">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="badge {{ $severityBadge[$sev] ?? 'badge-muted' }} uppercase tracking-[0.14em] text-[9px]">{{ $sev }}</span>
                        <span class="font-mono text-[11px] text-primary">{{ $group['title'] ?? $group['code'] }}</span>
                        <span class="font-mono text-[10px] text-muted">{{ $group['code'] }}</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 font-mono text-[10px]">
                        <span class="text-tertiary">{{ $group['category'] }}</span>
                        <span class="text-muted">·</span>
                        <span>
                            <span class="tabular-nums {{ $severityClass[$sev] ?? '' }}">{{ $group['affectedPageCount'] }}</span>
                            <span class="text-muted">{{ $group['affectedPageCount'] === 1 ? 'page' : 'pages' }}</span>
                        </span>
                        @if($group['count'] > $group['affectedPageCount'])
                            <span class="text-muted">·</span>
                            <span>
                                <span class="tabular-nums text-tertiary">{{ $group['count'] }}</span>
                                <span class="text-muted">occurrences</span>
                            </span>
                        @endif
                    </div>
                </header>

                {{-- Prose --}}
                <div class="p-4 font-mono text-[11px] leading-relaxed border-b border-line">
                    @if($hasProse)
                        <p class="text-secondary mb-3">{{ $group['summary'] }}</p>
                        <div class="space-y-1.5">
                            <div class="flex gap-2.5">
                                <span class="text-muted uppercase tracking-[0.14em] text-[9px] pt-0.5 shrink-0 w-10">why</span>
                                <span class="text-tertiary">{{ $group['why'] }}</span>
                            </div>
                            <div class="flex gap-2.5">
                                <span class="text-muted uppercase tracking-[0.14em] text-[9px] pt-0.5 shrink-0 w-10">how</span>
                                <span class="text-tertiary">{{ $group['how'] }}</span>
                            </div>
                            @if($group['source'])
                                <div class="flex gap-2.5 pt-1">
                                    <span class="text-muted uppercase tracking-[0.14em] text-[9px] pt-0.5 shrink-0 w-10">ref</span>
                                    <a href="{{ $group['source'] }}" target="_blank" rel="noopener"
                                       class="c-accent hover:underline truncate">{{ $group['source'] }}</a>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-tertiary">No catalog entry for <code class="text-secondary">{{ $group['code'] }}</code>.</p>
                    @endif
                </div>

                {{-- Affected pages --}}
                <div class="px-3 py-2 font-mono text-[11px]">
                    <div class="text-[9px] text-muted uppercase tracking-[0.14em] mb-1.5">affected pages</div>
                    <ul class="space-y-0.5">
                        @foreach($group['affectedPages'] as $ap)
                            <li class="flex items-baseline gap-2 min-w-0">
                                <span class="text-muted shrink-0">·</span>
                                <button wire:click="selectPage('{{ $ap['pageId'] }}')"
                                        class="text-secondary hover:c-accent transition-colors truncate text-left"
                                        title="{{ $ap['url'] }}">{{ $ap['url'] }}</button>
                                @if($ap['context'])
                                    <span class="text-muted shrink-0">→</span>
                                    <span class="text-tertiary truncate">{{ $ap['context'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endforeach

    </div>
    @endif
</div>
