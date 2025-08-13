<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ $this->getTitle() }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>

        <div class="user-content">
            @if($publisherLibraries->isNotEmpty())
                <ul>
                    @foreach($publisherLibraries as $media)
                        <li>
                            <a href="{{ route(App\Frontend\ScheduledConference\Pages\PublisherLibraryDownload::getRouteName(), ['media' => $media->uuid]) }}">{{ $media->getLocalizedMeta('name') }}</a>
                        </li>
                    @endforeach
                </ul>
            @else 
                <p>{{ __('general.no_publisher_library_available') }}</p>
            @endif
        </div>
    </div>
</x-website::layouts.main>