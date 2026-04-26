<div class="flex-1 flex flex-col min-h-0"
     @if($crawling) wire:poll.1s="poll" @endif>
    @include('livewire.partials.page-table')
    @include('livewire.partials.detail-panel')
</div>
