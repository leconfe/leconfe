<x-website::layouts.main>
    <div class="space-y-4 grid">
        <div class="grid justify-items-center">
            <h1 class="text-xl font-medium">{{ __('translation.searchBlade.searchConference') }}</h1>
        </div>
        <form class="max-w-xl w-full mx-auto space-y-2" x-data="{ advancedSearch: @js($isAdvancedSearch) }" wire:ignore.self>
            <div class="flex">
                <div class="relative w-full block">
                    <input 
                        wire:model.live.debounce.500ms="query"
                        type="search"  
                        placeholder="Search ..." 
                        class="block p-2.5 w-full z-20 text-sm text-gray-900 bg-gray-50 rounded border border-gray-300  focus:border-primary" 
                        required 
                    />
                    <button type="submit" class="absolute top-0 end-0 px-4 flex items-center gap-2 text-sm font-medium h-full text-white bg-primary rounded-e border border-primary">
                        <span class="">{{ __('translation.searchBlade.search') }}</span>
                        <x-heroicon-s-magnifying-glass class="h-4 w-4"/>
                    </button>
                </div>
            </div>
            <div class="buttons flex items-center">
                <div class="flex items-center gap-2" wire:loading.flex>
                    <span class="text-primary sr-only">{{ __('translation.searchBlade.searching') }}</span>
                    <span class="loading loading-spinner loading-sm text-primary"></span>
                </div>
                <button 
                    type="button" 
                    class="text-sm flex items-center gap-2 text-primary ml-auto"
                    x-on:click="advancedSearch = !advancedSearch"
                    >
                    {{ __('translation.searchBlade.advancedSearch') }} <x-heroicon-c-chevron-down class="h-4 w-4 transition-transform" ::class="advancedSearch && 'rotate-180'"/>
                </button>
            </div>
            <div class="space-y-4" x-show="advancedSearch" x-collapse x-cloak>
                <select 
                    id="topic" 
                    wire:model.live='topic'
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                    >
                    <option value selected>{{ __('translation.searchBlade.topics') }}</option>
                    @foreach ($topics as $topic)
                        <option value="{{ $topic->name }}">{{ $topic->name }}</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-4">
                    <button 
                        wire:click='clearAllSearch'
                        type="button" 
                        class="btn btn-primary btn-sm btn-outline">Clear All</button>
                    {{-- <button class="btn btn-primary btn-sm">Apply Filters</button> --}}
                </div>
            </div>
        </form>

        @if($searchResults->isNotEmpty())
        <div id="search-results" class="search-results grid lg:grid-cols-2 gap-6">
            @foreach ($searchResults as $serie)
                <x-website::serie-summary :serie="$serie" />
            @endforeach 
        </div>
        @if($searchResults->hasPages())
            <div>
                {{ $searchResults->links('livewire.simple-pagination') }}
            </div>
        @endif
        @else 
        <div class="flex items-center justify-center w-full">
            <p class="text-gray-500">{{ __('translation.searchBlade.noConferenceFound') }}</p>
        </div>
        @endif

    </div>
</x-website::layouts.main>