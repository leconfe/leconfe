<x-website::layouts.main>
    <div class="space-y-5">
        @if($site->getMeta('about'))
            <div class="description user-content">
                {{ new Illuminate\Support\HtmlString($site->getMeta('about')) }}
            </div>
        @endif

        <div class="conferences space-y-4" x-data="{tab: 'current'}" x-cloak>
            <div class="flex items-center justify-center text-sm flex-wrap">
                <div class="btn-group flex items-center shadow-sm overflow-x-scroll">
                    <button 
                        :class="{
                            'bg-primary text-primary-content' : tab === 'current',
                            'text-primary' : tab !== 'current',
                        }"
                        x-on:click="tab = 'current'"
                        class="w-40 p-2 border border-primary first:rounded-l last:rounded-r">
                        {{ __('translation.homeBlade.current') }}
                    </button>
                    <button 
                        :class="{
                            'bg-primary text-primary-content' : tab === 'upcoming',
                            'text-primary' : tab !== 'upcoming',
                        }"
                        x-on:click="tab = 'upcoming'"
                        class="w-40 p-2 border-y border-primary first:rounded-l last:rounded-r">
                        {{ __('translation.homeBlade.upcoming') }}
                    </button>
                    <button 
                        :class="{
                            'bg-primary text-primary-content' : tab === 'allconferences',
                            'text-primary' : tab !== 'allconferences',
                        }"
                        x-on:click="tab = 'allconferences'"
                        class="w-40 p-2 border border-primary first:rounded-l last:rounded-r text-nowrap">
                        {{ __('translation.homeBlade.allConferences') }}
                    </button>
                    <a 
                        href="{{ route('livewirePageGroup.website.pages.search') }}"  
                        class="w-40 p-2 border border-l-0 border-primary text-primary first:rounded-l last:rounded-r flex items-center justify-center gap-2">
                        <x-heroicon-s-magnifying-glass class="h-4 w-4"/>
                        {{ __('translation.homeBlade.search') }}
                    </a>
                </div>
            </div>
            <div class="conference-current space-y-4" x-show="tab === 'current'">
                @if($currentSeries->isNotEmpty())
                    <div class="grid lg:grid-cols-2 gap-6">
                        @foreach ($currentSeries as $serie)
                            <x-website::serie-summary :serie="$serie" />
                        @endforeach
                    </div>
                    @if($currentSeries->hasPages())
                        {{ $currentSeries->links('livewire.simple-pagination') }}
                    @endif
                @else
                    <div class="text-center my-12">
                        <p class="text-lg font-bold">{{ __('translation.homeBlade.thereAreNoConferencesTakingPlaceAtThisTime') }}</p>
                    </div>
                @endif
            </div>
            <div class="conference-upcoming space-y-4" x-show="tab === 'upcoming'">
                @if($upcomingSeries->isNotEmpty())
                    <div class="grid lg:grid-cols-2 gap-6">
                        @foreach ($upcomingSeries as $serie)
                            <x-website::serie-summary :serie="$serie" />
                        @endforeach
                    </div>
                    @if($upcomingSeries->hasPages())
                        {{ $upcomingSeries->links('livewire.simple-pagination') }}
                    @endif
                @else
                    <div class="text-center my-12">
                        <p class="text-lg font-bold">{{ __('translation.homeBlade.thereAreNoUpComingConferencesTakingPlaceAtThisTime') }}</p>
                    </div>
                @endif
            </div>
            <div class="conference-all space-y-4" x-show="tab === 'allconferences'">
                @if($allSeries->isNotEmpty())
                    <div class="grid lg:grid-cols-2 gap-6">
                        @foreach ($allSeries as $serie)
                            <x-website::serie-summary :serie="$serie" />
                        @endforeach
                    </div>
                    @if($allSeries->hasPages())
                        {{ $allSeries->links('livewire.simple-pagination') }}
                    @endif
                @else
                    <div class="text-center my-12">
                        <p class="text-lg font-bold">{{ __('translation.homeBlade.thereAreNoConferences') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-website::layouts.main>
