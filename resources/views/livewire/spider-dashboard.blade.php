<div class="h-screen flex flex-col" @if($crawling) wire:poll.1s="poll" @endif>

    @include('livewire.partials.header')

    <div class="flex-1 flex min-h-0">
        @include('livewire.partials.sidebar')

        <main class="flex-1 flex flex-col min-h-0 min-w-0">
            @include('livewire.partials.page-table')
            @include('livewire.partials.detail-panel')

            {{-- Footer --}}
            <footer class="flex-none h-7 bg-panel2 border-t border-line px-3 flex items-center justify-between text-2xs text-tertiary tabular-nums">
                <span class="flex items-center gap-2">
                    {{ count($this->filteredPages) }} pages
                    @if($activeTab !== 'all') <span class="text-muted">({{ count($pages) }} total)</span> @endif
                    @if($searchQuery) <span class="c-accent">matching "{{ $searchQuery }}"</span> @endif
                </span>
                <span class="flex items-center gap-3">
                    @if($crawling && count($newPageIds) > 0)
                        <span class="c-accent flex items-center gap-1">
                            <span class="w-1 h-1 rounded-full dot-pulse" style="background:var(--c-accent)"></span>
                            +{{ count($newPageIds) }} new
                        </span>
                    @endif
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
