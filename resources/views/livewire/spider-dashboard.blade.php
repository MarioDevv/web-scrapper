<div class="max-w-6xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-8">🕷️ SEO Spider</h1>

    {{-- Formulario --}}
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <div class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm text-gray-400 mb-1">URL to crawl</label>
                <input type="url" wire:model="url" placeholder="https://example.com"
                       class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                        @disabled($crawling)>
            </div>
            <div class="w-32">
                <label class="block text-sm text-gray-400 mb-1">Max pages</label>
                <input type="number" wire:model="maxPages"
                       class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                        @disabled($crawling)>
            </div>
            <div class="w-32">
                <label class="block text-sm text-gray-400 mb-1">Max depth</label>
                <input type="number" wire:model="maxDepth"
                       class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                        @disabled($crawling)>
            </div>
            <button wire:click="startCrawl"
                    class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white font-semibold px-6 py-2 rounded transition"
                    @disabled($crawling)>
                @if($crawling)
                    <span class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Crawling...
                    </span>
                @else
                    Start Crawl
                @endif
            </button>
        </div>
        @error('url') <p class="text-red-400 text-sm mt-2">{{ $message }}</p> @enderror
    </div>

    {{-- Status --}}
    @if($status)
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-400">Status</div>
                <div class="text-xl font-bold {{ $status['status'] === 'completed' ? 'text-green-400' : 'text-yellow-400' }}">
                    {{ ucfirst($status['status']) }}
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-400">Pages Crawled</div>
                <div class="text-xl font-bold">{{ $status['pagesCrawled'] }}</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-400">Issues</div>
                <div class="text-xl font-bold">
                    <span class="text-red-400">{{ $status['errorsFound'] }} err</span>
                    /
                    <span class="text-yellow-400">{{ $status['warningsFound'] }} warn</span>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-400">Duration</div>
                <div class="text-xl font-bold">
                    {{ $status['duration'] ? number_format($status['duration'], 1) . 's' : '-' }}
                </div>
            </div>
        </div>
    @endif

    {{-- Pages table --}}
    @if(count($pages) > 0)
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-700">
                <tr>
                    <th class="text-left p-3 text-gray-400">URL</th>
                    <th class="text-center p-3 text-gray-400 w-20">Status</th>
                    <th class="text-left p-3 text-gray-400">Title</th>
                    <th class="text-center p-3 text-gray-400 w-20">Time</th>
                    <th class="text-center p-3 text-gray-400 w-24">Issues</th>
                    <th class="text-center p-3 text-gray-400 w-24">Indexable</th>
                </tr>
                </thead>
                <tbody>
                @foreach($pages as $page)
                    <tr class="border-t border-gray-700 hover:bg-gray-750">
                        <td class="p-3 truncate max-w-xs" title="{{ $page['url'] }}">{{ $page['url'] }}</td>
                        <td class="p-3 text-center">
                        <span class="px-2 py-0.5 rounded text-xs font-mono
                            {{ $page['statusCode'] === 200 ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' }}">
                            {{ $page['statusCode'] }}
                        </span>
                        </td>
                        <td class="p-3 truncate max-w-xs">{{ $page['title'] ?? '-' }}</td>
                        <td class="p-3 text-center text-gray-400">{{ number_format($page['responseTime'], 0) }}ms</td>
                        <td class="p-3 text-center">
                            @if($page['errorCount'] > 0)
                                <span class="text-red-400">{{ $page['errorCount'] }}E</span>
                            @endif
                            @if($page['warningCount'] > 0)
                                <span class="text-yellow-400">{{ $page['warningCount'] }}W</span>
                            @endif
                            @if($page['errorCount'] === 0 && $page['warningCount'] === 0)
                                <span class="text-green-400">✓</span>
                            @endif
                        </td>
                        <td class="p-3 text-center">
                            @if($page['isIndexable'])
                                <span class="text-green-400">Yes</span>
                            @else
                                <span class="text-red-400">No</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>