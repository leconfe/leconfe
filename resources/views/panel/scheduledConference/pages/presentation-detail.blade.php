<div class="my-4">
    <div class="space-y-4">
        <div class="grid xl:grid-cols-3 ">
            <div class="xl:col-span-2 fi-contained rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                @if ($record->type === \App\Models\Enums\PresentationType::Other)
                    <div class="rounded-t-xl border-b border-gray-200 bg-gray-50 p-8">
                        <div class="mx-auto max-w-xl text-center">
                            <x-heroicon-o-arrow-down-tray class="mx-auto h-10 w-10 text-primary-600" />
                            <h2 class="mt-3 text-base font-semibold text-gray-900">
                                {{ __('scheduled_conference.presentation_other_no_preview_title') }}
                            </h2>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ __('scheduled_conference.presentation_other_no_preview_description') }}
                            </p>

                            @if ($record->getDownloadUrl())
                                <a
                                    href="{{ $record->getDownloadUrl() }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="fi-btn fi-btn-size-sm fi-btn-color-primary mt-4 inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition duration-75"
                                >
                                    {{ __('scheduled_conference.presentation_other_download_button') }}
                                </a>
                            @else
                                <p class="mt-3 text-xs text-gray-500">
                                    {{ __('scheduled_conference.presentation_other_file_not_available') }}
                                </p>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="container-iframe-16-9 rounded-t-xl">
                        <iframe class="responsive-iframe" src="{{ $record->getIframeUrl() }}" frameborder="0" allowfullscreen="true" mozallowfullscreen="true" webkitallowfullscreen="true"></iframe>
                    </div>
                @endif
                <div class="py-2.5 px-3">
                    <h1 class="text-lg font-medium">
                        {{ $record->submission->getMeta('title') }}
                    </h1>

                    @php
                        $submission = $record->submission;
                        $primaryAuthor = $submission->authors->first(fn ($author) => $author->isPrimaryContact($submission));
                    @endphp

                    @if ($primaryAuthor)
                        <p class="text-sm text-gray-500 inline-flex items-center gap-1">
                            <x-heroicon-m-user class="w-3.5 h-3.5 text-gray-400" />
                            <span>{{ $primaryAuthor->fullName }}</span>
                        </p>
                    @endif

                    <div
                        class="mt-3 flex flex-wrap items-center gap-2"
                        x-data="presentationEngagement({
                            hasLiked: @js($record->isLikedBy((int) auth()->id())),
                            likesCount: @js($record->likesCountMeta()),
                            viewsCount: @js($record->viewsCountMeta()),
                        })"
                    >
                        <button
                            type="button"
                            disabled
                            class="fi-btn fi-btn-size-xs inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-2 py-1 text-sm font-semibold ring-1 transition duration-75 fi-btn-color-gray fi-btn-outlined ring-gray-300 text-gray-700"
                        >
                            <x-heroicon-o-eye class="h-4 w-4" />
                            <span x-text="viewsCount"></span>
                        </button>
                        <button
                            type="button"
                            x-on:click="toggleLike"
                            x-bind:disabled="pending"
                            x-bind:class="hasLiked
                                ? 'ring-danger-300 text-danger-600 fi-btn-color-danger'
                                : 'ring-gray-300 text-gray-700 fi-btn-color-gray'"
                            class="fi-btn fi-btn-size-xs inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-2 py-1 text-sm font-semibold ring-1 transition duration-75 fi-btn-outlined"
                        >
                            <x-heroicon-s-heart x-show="hasLiked" class="h-4 w-4" x-cloak />
                            <x-heroicon-o-heart x-show="!hasLiked" class="h-4 w-4" x-cloak />
                            <span x-text="likesCount"></span>
                        </button>
                    </div>
                </div>
                <div class="fi-in-tabs flex flex-col"
                    x-data="{ activeTab: 'discussion-tab' }">
                    <x-filament::tabs :contained="true" class="!mx-0 border-t">
                        <x-filament::tabs.item 
                            alpine-active="activeTab === 'discussion-tab'"
                            x-on:click="activeTab = 'discussion-tab'"
                            >
                            Discussion
                        </x-filament::tabs.item>
            
                        <x-filament::tabs.item 
                            alpine-active="activeTab === 'abstract-tab'"
                            x-on:click="activeTab = 'abstract-tab'"
                        >
                            {{ __('general.abstract') }}
                        </x-filament::tabs.item>
            
                        <x-filament::tabs.item x
                            alpine-active="activeTab === 'authors-tab'"
                            x-on:click="activeTab = 'authors-tab'"
                        >
                            Authors
                        </x-filament::tabs.item>
                    </x-filament::tabs>
                    <div class="py-2.5 px-3" x-show="activeTab === 'discussion-tab'">
                        @livewire(App\Panel\ScheduledConference\Livewire\PresentationDiscussion::class, ['record' => $record, 'key' => "comment-$record->getKey()"])
                    </div>
                    <div class="py-2.5 px-3" x-show="activeTab === 'abstract-tab'" x-cloak>
                        <div class="citation_abstract content user-content text-sm">
                            {!! $record->submission->getMeta('abstract') !!}
                        </div>
                    </div>
                    <div class="py-2.5 px-3" x-show="activeTab === 'authors-tab'" x-cloak>
                         <div
                            class="grid gap-4">
                            @foreach ($record->submission->authors as $author)
                                <div class="col-span-2 sm:col-span-1">
                                    <div class="flex items-center">
                                        <x-lineawesome-user class="w-4 h-4 mr-1" />
                                        <h3 class="author-name text-sm">{{ $author->fullName }}</h3>
                                    </div>
                                    @if($author->getMeta('affiliation'))
                                        <div class="ml-[20px] text-xs text-slate-500">{{ $author->getMeta('affiliation') }}</div>
                                    @endif
                                    <div class="ml-[20px] text-xs text-slate-500">{{ $author->role->name }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
    
