<div class="flex-1 overflow-auto min-h-0">
    <div class="p-5 space-y-5 max-w-6xl">

        @if($diffUnavailable)
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">── diff/unavailable ──────</span>
                </div>
                <div class="p-4 text-[12px] font-mono text-tertiary">
                    No hay un audit anterior completado para este dominio. Lanza otro audit del mismo host y vuelve a comparar.
                </div>
            </section>
        @elseif($diff === null)
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">── diff/loading ──────</span>
                </div>
                <div class="p-4 text-[12px] font-mono text-tertiary">
                    Cargando comparación…
                </div>
            </section>
        @else
            {{-- ── HEADER ─────────────────────────────────────────────── --}}
            <section class="bg-app2 border border-line">
                <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] c-accent">── diff/summary ──────</span>
                    <span class="font-mono text-[10px] text-muted">{{ $diff['host'] }}</span>
                </div>
                <div class="grid grid-cols-4 divide-x divide-[var(--c-border)]">
                    @php
                        $diffCards = [
                            ['pages_added',     $diff['pagesAddedCount'],     $diff['pagesAddedCount']   > 0 ? 'var(--c-ok)'   : null],
                            ['pages_removed',   $diff['pagesRemovedCount'],   $diff['pagesRemovedCount'] > 0 ? 'var(--c-err)'  : null],
                            ['pages_moved',     $diff['pagesMovedCount'],     $diff['pagesMovedCount']   > 0 ? 'var(--c-warn)' : null],
                            ['pages_unchanged', $diff['pagesUnchangedCount'], null],
                        ];
                    @endphp
                    @foreach($diffCards as [$label, $value, $color])
                        <div class="px-4 py-3 stat-card">
                            <div class="text-[10px] font-mono uppercase tracking-[0.16em] text-muted mb-1.5">{{ $label }}</div>
                            <div class="text-[22px] font-mono font-medium tabular-nums leading-none"
                                 style="{{ $color ? "color: {$color}" : 'color: var(--c-fg)' }}">
                                {{ number_format($value) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-3 py-2 border-t border-line bg-panel2 flex items-center justify-between text-[10px] font-mono text-muted">
                    <span>base · {{ $diff['baseCompletedAt'] }}</span>
                    <span class="c-accent">→</span>
                    <span>target · {{ $diff['targetCompletedAt'] }}</span>
                </div>
            </section>

            {{-- ── ISSUE SECTIONS ─────────────────────────────────────── --}}
            @php
                $sections = [
                    ['label' => 'issues_added',      'rows' => $diff['issuesAdded'],      'accentVar' => 'var(--c-err)'],
                    ['label' => 'issues_resolved',   'rows' => $diff['issuesRemoved'],    'accentVar' => 'var(--c-ok)'],
                    ['label' => 'issues_persistent', 'rows' => $diff['issuesPersistent'], 'accentVar' => 'var(--c-warn)'],
                ];
            @endphp

            @foreach($sections as $section)
                <section class="bg-app2 border border-line">
                    <div class="flex items-center justify-between px-3 h-7 border-b border-line bg-panel2">
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em]" style="color: {{ $section['accentVar'] }}">
                            ── {{ $section['label'] }} ──────
                        </span>
                        <span class="font-mono text-[10px] text-muted tabular-nums">{{ count($section['rows']) }}</span>
                    </div>
                    @if(count($section['rows']) === 0)
                        <div class="p-3 text-[11px] font-mono text-muted">—</div>
                    @else
                        <ul class="divide-y divide-[var(--c-border)]">
                            @foreach($section['rows'] as $row)
                                <li class="px-3 py-2 flex items-baseline gap-2 text-[11px] font-mono">
                                    <span class="text-muted uppercase text-[10px] tracking-[0.12em] w-16 shrink-0">
                                        {{ $row['severity'] }}
                                    </span>
                                    <span class="c-accent shrink-0">{{ $row['code'] }}</span>
                                    <span class="text-secondary truncate">— {{ $row['title'] }}</span>
                                    <span class="text-tertiary truncate ml-auto">
                                        {{ $row['pageUrl'] }}
                                        @if(!empty($row['movedFromUrl']))
                                            <span class="text-muted">(antes {{ $row['movedFromUrl'] }})</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        @endif
    </div>
</div>
