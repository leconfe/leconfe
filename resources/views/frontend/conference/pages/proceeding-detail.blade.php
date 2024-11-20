<x-website::layouts.main>
    <div class="space-y-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
        @if (!$proceeding->isPublished())
            <div role="alert" class="gap-2.5 alert bg-yellow-200/20 border-yellow-200/40">
                <x-heroicon-s-eye class="w-5 h-5 my-auto text-amber-600" />
                <div class="text-amber-900">
                    <span class="text-amber-500">Preview. </span> This proceeding is not published yet. This is only the preview of the proceeding.
                </div>
            </div>
        @endif
       
        <div class="proceeding-toc space-y-6">
            <div class="proceeding-detail">
                <x-website::heading-title tag="h1" :title="$proceeding->seriesTitle()" class="mb-5" />
                <div class="flex flex-col sm:flex-row gap-4">
                    @if($proceeding->getFirstMediaUrl('cover'))
                        <div class="cover max-w-56 grow">
                            <img src="{{ $proceeding->getFirstMediaUrl('cover') }}" class="w-full" alt="Proceeding Cover">
                        </div>
                    @endif
                    <div class="flex-1">
                        <div class="space-y-4">
                            <div class="user-content">
                                {{ new Illuminate\Support\HtmlString($proceeding->description) }}
                            </div>
                            <div class="text-sm">
                                <span class="font-semibold">Published: </span> {{ $proceeding->published_at ? $proceeding->published_at->format(Setting::get('format_date')) : '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tracks space-y-6">
                @foreach ($tracks as $track)    
                    <div class="track space-y-4">
                        <x-website::heading-title :title="$track->title" class="track-title"/>
                        <div class="paper-summaries space-y-4">
                            @forelse($track->submissions as $paper)
                                <x-conference::paper-summary :paper="$paper"/>  
                            @empty
                                <div class="text-center text-gray-500">
                                    No paper found.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-website::layouts.main>