<x-website::layouts.main>
    <div class="space-y-5">
        @if ($site->getMeta('about'))
            <div class="description user-content">
                {{ new Illuminate\Support\HtmlString($site->getMeta('about')) }}
            </div>
        @endif

        @if($featuredScheduledConferences->isNotEmpty())
            <div class="featured-scheduled-conference">
                <x-website::heading-title title="{{ __('general.featured_scheduled_conference') }}" class="grow" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 py-4">
                    @foreach ($featuredScheduledConferences as $scheduledConference)
                        <x-website::scheduled-conference-summary :scheduledConference="$scheduledConference" />
                    @endforeach
                </div>
            </div>
        @endif


        <div class="featured-scheduled-conference">
            <x-website::heading-title title="{{ __('general.conferences') }}" class="grow" />

            <div class="mt-6 mb-6 grid grid-cols-10 gap-2 items-center">
                <div class="md:col-span-5 col-span-full gap-2">
                    <label class="input input-sm input-bordered !outline-none bg-white flex items-center gap-2">
                        <input type="search" class="grow" placeholder="{{ __('general.search') }}"
                            wire:model.live.debounce="filter.search.value" />
                        <x-heroicon-m-magnifying-glass class="h-4 w-4 opacity-70" />
                    </label>
                </div>

                <div class="col-span-full sm:col-span-5 md:col-span-2 dropdown h-fit w-full" x-data="{ open: false }">
                    <button tabindex="0" role="button" class="btn btn-sm btn-outline border-gray-300 w-full"
                        x-ref="button" @@click="$wire.call('changeStateLoadCategories', true); open = ! open">
                        {{ __('general.categories') }} <x-heroicon-o-chevron-down class="h-4 w-4" />
                    </button>

                    <div tabindex="0"
                        class="mt-2 p-2 pt-0 max-w-fit min-w-full grid bg-white border rounded z-[1] shadow-xl max-h-72 overflow-auto relative"
                        x-show="open" x-on:click.outside="open = false; $wire.call('changeStateLoadCategories', false)"
                        x-on:mouseleave="open = false; $wire.call('changeStateLoadCategories', false)"
                        x-anchor="$refs.button" x-cloak>
                        <div class="sticky top-0 bg-white z-10 pt-2">
                            <label class="mb-2 input input-xs input-bordered !outline-none bg-white flex items-center">
                                <input type="search" class="grow" placeholder="{{ __('general.search') }}"
                                    wire:model.live.debounce="filter.category.search" />
                                <x-heroicon-m-magnifying-glass class="h-3 w-3 opacity-70" />
                            </label>
                            <button class="mb-2 btn btn-xs btn-outline no-animation border-neutral-300 w-full"
                                wire:click="resetFilter('category')" wire:loading.attr="disabled">
                                {{ __('general.reset') }}
                            </button>
                        </div>
                        <div wire:loading class="p-2 text-center text-sm">
                            {{ __('general.loading') }}
                        </div>
                        <div wire:loading.remove>
                            @foreach ($categories as $id => $name)
                                <div>
                                    <label
                                        class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                        <span class="label-text px-2">{{ $name }}
                                        </span>
                                        <input type="checkbox" class="checkbox checkbox-xs mx-1.5" value="{{ $name }}"
                                            wire:model.live="filter.category.value" wire:key="{{ $id }}" />
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-span-full sm:col-span-5 md:col-span-2 dropdown h-fit w-full" x-data="{ open: false }">
                    <button tabindex="0" role="button" class="btn btn-sm btn-outline border-gray-300 w-full"
                        x-ref="button" @@click="$wire.call('changeStateLoadFaculties', true); open = ! open">
                        {{ __('general.faculties') }} <x-heroicon-o-chevron-down class="h-4 w-4" />
                    </button>

                    <div tabindex="0"
                        class="mt-2 p-2 pt-0 max-w-fit min-w-full grid bg-white border rounded z-[1] shadow-xl max-h-72 overflow-auto relative"
                        x-show="open" x-on:click.outside="open = false; $wire.call('changeStateLoadFaculties', false)"
                        x-on:mouseleave="open = false; $wire.call('changeStateLoadFaculties', false)"
                        x-anchor="$refs.button" x-cloak>
                        <div class="sticky top-0 bg-white z-10 pt-2">
                            <label class="mb-2 input input-xs input-bordered !outline-none bg-white flex items-center">
                                <input type="search" class="grow" placeholder="{{ __('general.search') }}"
                                    wire:model.live.debounce="filter.faculty.search" />
                                <x-heroicon-m-magnifying-glass class="h-3 w-3 opacity-70" />
                            </label>
                            <button class="mb-2 btn btn-xs btn-outline no-animation border-neutral-300 w-full"
                                wire:click="resetFilter('faculty')" wire:loading.attr="disabled">
                                {{ __('general.reset') }}
                            </button>
                        </div>
                        <div wire:loading class="p-2 text-center text-sm">
                            {{ __('general.loading') }}
                        </div>
                        <div wire:loading.remove>
                            @foreach ($faculties as $id => $name)
                                <div>
                                    <label
                                        class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                        <span class="label-text px-2">{{ $name }}
                                        </span>
                                        <input type="checkbox" class="checkbox checkbox-xs mx-1.5" value="{{ $name }}"
                                            wire:model.live="filter.faculty.value" wire:key="{{ $id }}" />
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <button class="col-span-full md:col-span-1 btn btn-sm btn-primary w-full tooltip"
                    data-tip="Clear all the filter and the search input." wire:click="resetFilter"
                    wire:loading.attr="disabled" wire:ignore>
                    {{ __('general.reset_all') }}
                </button>

                <div class="col-span-full w-full">
                    @if (!empty($filter['category']['value']))
                        <span class="px-3 py-0.5 badge badge-primary text-xs">
                            {{ __('general.category') }}: {{ implode(', ', $filter['category']['value']) }}
                            <span class="ml-2">
                                <x-heroicon-o-x-mark class="h-3 w-3 cursor-pointer hover:text-neutral"
                                    wire:click="resetFilter('category')" />
                            </span>
                        </span>
                    @endif

                    @if (!empty($filter['faculty']['value']))
                        <span class="px-3 py-0.5 badge badge-primary text-xs">
                            {{ __('general.faculty') }}: {{ implode(', ', $filter['faculty']['value']) }}
                            <span class="ml-2">
                                <x-heroicon-o-x-mark class="h-3 w-3 cursor-pointer hover:text-neutral"
                                    wire:click="resetFilter('faculty')" />
                            </span>
                        </span>
                    @endif

                    @if (!empty($selectedConferences))
                        <span class="px-3 py-0.5 badge badge-primary text-xs">
                            {{ __('general.conference') }}: {{ implode(', ', $selectedConferences) }}
                            <span class="ml-2">
                                <x-heroicon-o-x-mark class="h-3 w-3 cursor-pointer hover:text-neutral"
                                    wire:click="resetFilter('conference')" />
                            </span>
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 py-4">
                @forelse ($scheduledConferences as $scheduledConference)
                    <x-website::scheduled-conference-summary :scheduledConference="$scheduledConference" />
                @empty
                    <div class="my-12 text-center">
                        <p class="text-lg font-bold">{{ __('general.there_are_no_conferences_taking_place_at_this_time') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-website::layouts.main>