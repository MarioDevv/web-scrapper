<div class="h-screen flex flex-col" @if($crawling) wire:poll.1s="poll" @endif>

    @include('livewire.partials.header')

    <div class="flex-1 flex min-h-0">
        @include('livewire.partials.sidebar')

        <main class="flex-1 flex flex-col min-h-0 min-w-0">
            @include('livewire.partials.page-table')
            @include('livewire.partials.detail-panel')

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{--  STATUS BAR — tmux-style                                    --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <footer class="flex-none h-7 app-no-drag chrome-nosel bg-panel2 border-t border-line2 px-3
                           flex items-center justify-between text-[10px] font-mono tabular-nums leading-none">

                {{-- ── LEFT: session info ── --}}
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex items-center gap-1.5 shrink-0">
                        <span class="c-accent" style="text-shadow:0 0 4px var(--c-accent-glow);">▸</span>
                        <span class="text-muted uppercase tracking-[0.14em]">session</span>
                    </span>
                    <span class="text-secondary truncate">
                        {{ $status ? (parse_url($status['seedUrl'] ?? '', PHP_URL_HOST) ?: '—') : 'idle' }}
                    </span>

                    @if($status && $status['duration'])
                        <span class="text-muted shrink-0">│</span>
                        <span class="shrink-0">
                            <span class="text-muted">t+</span><span class="text-primary">{{ gmdate('i:s', (int)$status['duration']) }}</span>
                        </span>
                    @endif

                    @if($crawling && $this->progress['rate'] > 0)
                        <span class="text-muted shrink-0">│</span>
                        <span class="shrink-0">
                            <span class="text-primary">{{ $this->progress['rate'] }}</span><span class="text-muted">p/s</span>
                        </span>
                    @endif
                </div>

                {{-- ── RIGHT: counts + state ── --}}
                <div class="flex items-center gap-2.5 shrink-0">

                    @if($searchQuery)
                        <span class="c-accent flex items-center gap-1">
                            <span class="text-muted">/</span>{{ $searchQuery }}
                        </span>
                        <span class="text-muted">│</span>
                    @endif

                    @php
                        $useTotal    = in_array($activeTab, ['overview', 'audit', 'all'], true);
                        $displayCnt  = $useTotal ? count($pages) : count($this->filteredPages);
                        $showFilter  = !$useTotal && count($this->filteredPages) !== count($pages);
                    @endphp
                    <span>
                        <span class="text-primary">{{ $displayCnt }}</span>@if($showFilter)<span class="text-muted">/</span><span class="text-tertiary">{{ count($pages) }}</span>@endif
                        <span class="text-muted ml-1">pages</span>
                    </span>

                    @if($crawling && count($newPageIds) > 0)
                        <span class="text-muted">│</span>
                        <span class="c-accent flex items-center gap-1">
                            <span class="w-1 h-1 rounded-full dot-pulse" style="background:var(--c-accent); box-shadow:0 0 4px var(--c-accent-glow);"></span>
                            +{{ count($newPageIds) }}
                        </span>
                    @endif

                    @if($status && $status['errorsFound'] > 0)
                        <span class="text-muted">│</span>
                        <span class="c-err">{{ $status['errorsFound'] }}<span class="text-muted ml-0.5">err</span></span>
                    @endif
                    @if($status && $status['warningsFound'] > 0)
                        <span class="c-warn">{{ $status['warningsFound'] }}<span class="text-muted ml-0.5">warn</span></span>
                    @endif

                    <span class="text-muted">│</span>

                    {{-- State pill --}}
                    @if($crawling)
                        <span class="flex items-center gap-1.5 c-accent uppercase tracking-[0.16em] font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full dot-pulse" style="background:var(--c-accent); box-shadow:0 0 6px var(--c-accent-glow);"></span>
                            live
                        </span>
                    @elseif($paused)
                        <span class="flex items-center gap-1.5 c-warn uppercase tracking-[0.16em] font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c-warn);"></span>
                            paused
                        </span>
                    @elseif($status)
                        @php
                            $isDone = $status['status'] === 'completed';
                            $stateColor = $isDone ? 'var(--c-ok)' : 'var(--c-err)';
                            $stateGlyph = match($status['status']) { 'completed' => '✓', 'cancelled' => '■', 'failed' => '✗', default => '·' };
                            $stateLabel = match($status['status']) { 'completed' => 'done', 'cancelled' => 'stop', 'failed' => 'fail', default => strtolower($status['status']) };
                        @endphp
                        <span class="flex items-center gap-1.5 uppercase tracking-[0.16em] font-semibold" style="color: {{ $stateColor }};">
                            <span>{{ $stateGlyph }}</span>
                            {{ $stateLabel }}
                        </span>
                    @else
                        <span class="flex items-center gap-1.5 text-tertiary uppercase tracking-[0.16em]">
                            <span class="w-1.5 h-1.5 rounded-full" style="background: var(--c-fg4);"></span>
                            ready
                        </span>
                    @endif
                </div>
            </footer>
        </main>
    </div>
</div>
