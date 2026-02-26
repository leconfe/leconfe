<x-filament-panels::page>
    <div class="w-full">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>
    </div>

    @if ($presentations->count())
        <div class="grid sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 3xl:grid-cols-5 gap-4 items-stretch">
            @foreach ($presentations as $presentation)
                <a href="{{ $presentation->url() }}" class="group block h-full">
                    <div
                        class="bg-white h-full rounded-lg shadow-xs border border-primary-100 group-hover:border-primary-700 transition-colors flex flex-col">

                        @if($presentation->hasMedia('thumbnail'))
                            <img class="rounded-t-lg w-full aspect-[16/9] object-cover"
                                src="{{ $presentation->getFirstMediaUrl('thumbnail') }}" alt="" />
                        @else 
                            <div class="rounded-t-lg w-full aspect-[16/9] bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                <x-heroicon-o-computer-desktop class="w-12 h-12 text-gray-400"/>
                            </div>
                        @endif

                        <div class="p-6 flex flex-col gap-4 flex-1">
                            <div class="inline-flex w-fit items-center border text-xs font-medium px-1.5 py-0.5 rounded-sm">
                                {{ $presentation->submission->track->title }}
                            </div>

                            <h3
                                class="text-sm font-semibold tracking-tight text-gray-700 group-hover:text-black line-clamp-3">
                                {{ $presentation->submission->getMeta('title') }}
                            </h3>

                            @php
                                $submission = $presentation->submission;
                                $primaryAuthor = $submission->authors->first(fn ($author) => $author->isPrimaryContact($submission));
                            @endphp

                            @if ($primaryAuthor)
                                <p class="text-xs text-gray-500 inline-flex items-center gap-1">
                                    <x-heroicon-m-user class="w-3.5 h-3.5 text-gray-400" />
                                    <span>{{ $primaryAuthor->fullName }}</span>
                                </p>
                            @endif

                            <div class="inline-flex items-center gap-3 text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-o-eye class="h-3.5 w-3.5" />
                                    <span>{{ $presentation->viewsCountMeta() }}</span>
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-o-heart class="h-3.5 w-3.5" />
                                    <span>{{ $presentation->likesCountMeta() }}</span>
                                </span>
                            </div>

                            <div class="mt-auto">
                                <x-filament::button 
                                    icon="heroicon-m-arrow-right"
                                    size="xs"
                                >
                                    Read More
                                </x-filament::button>
                            </div>
                        </div>

                    </div>
                </a>
            @endforeach
        </div>
    @else
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-3 py-8 text-center">
                <x-heroicon-m-magnifying-glass class="w-8 h-8 text-gray-400" />
                <div class="text-sm font-medium text-gray-700">No presentations found</div>
                <div class="text-xs text-gray-500">Try adjusting your filters or search terms.</div>
            </div>
        </x-filament::section>
    @endif

    <div class="w-full">
        {{ $presentations->links('panel.scheduledConference.pagination.presentations') }}
    </div>
</x-filament-panels::page>
