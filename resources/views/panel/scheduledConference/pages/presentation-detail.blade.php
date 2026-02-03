<x-filament-panels::page>
    <div class="space-y-4">
        <div class="grid xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-4">
                <div class="container-iframe-16-9 rounded-xl">
                    <iframe class="responsive-iframe" src="{{ $record->getIframeUrl() }}" frameborder="0" allowfullscreen="true" mozallowfullscreen="true" webkitallowfullscreen="true"></iframe>
                </div>
                <div class="fi-in-tabs flex flex-col fi-contained rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5"
                    x-data="{ activeTab: 'discussion-tab' }">
                    <x-filament::tabs :contained="true" class="!mx-0">
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
                    <div class="p-6" x-show="activeTab === 'discussion-tab'">
                        @livewire(App\Panel\ScheduledConference\Livewire\PresentationDiscussion::class, ['record' => $record, 'key' => "comment-$record->getKey()"])
                    </div>
                    <div class="p-6" x-show="activeTab === 'abstract-tab'" x-cloak>
                        <div class="citation_abstract content user-content text-sm">
                            {!! $record->submission->getMeta('abstract') !!}
                        </div>
                    </div>
                    <div class="p-6" x-show="activeTab === 'authors-tab'" x-cloak>
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
</x-filament-panels::page>
    