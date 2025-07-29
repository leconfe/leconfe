<x-website::layouts.main>
    <div class="space-y-5">
        @if ($site->getMeta('about'))
            <div class="description user-content">
                {{ new Illuminate\Support\HtmlString($site->getMeta('about')) }}
            </div>
        @endif

        @if($featuredScheduledConferences->isNotEmpty())
            <div class="featured-scheduled-conference">
                <x-website::heading-title title="{{ __('general.featured_scheduled_conference') }}" class="grow"/>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4">
                    @foreach ($featuredScheduledConferences as $scheduledConference)
                        <x-website::scheduled-conference-summary :scheduledConference="$scheduledConference" />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-y-4 conferences">

            <x-website::heading-title title="{{ __('general.conference_list') }}" class="grow"/>

            <div class="mt-6 mb-6 grid grid-cols-10 gap-2">
                <div class="col-span-full gap-2">
                    <label class="input input-sm input-bordered !outline-none bg-white flex items-center gap-2">
                        <input type="search" class="grow" placeholder="{{ __('general.search') }}" wire:model.live.debounce="filter.search.value" />
                        <x-heroicon-m-magnifying-glass class="h-4 w-4 opacity-70" />
                    </label>
                </div>

                <div class="col-span-full sm:col-span-5 md:col-span-2 dropdown h-fit w-full" x-data="{ open: false }">
                    <button tabindex="0" role="button" class="btn btn-sm btn-outline border-gray-300 w-full" x-ref="button" @@click="open = ! open">
                        {{ __('general.scope') }} <x-heroicon-o-chevron-down class="h-4 w-4" />
                    </button>

                   <div tabindex="0" 
                        class="mt-2 p-2 max-w-fit grid bg-white border rounded z-[1] shadow-xl" 
                        x-show="open" 
                        x-on:click.outside="open = false;"
                        x-on:mouseleave="open = false" 
                        x-anchor="$refs.button">
                        <div>
                            <button class="mb-2 btn btn-xs btn-outlin no-animation border-neutral-300 w-full" wire:click="resetFilter('scope')" wire:loading.attr="disabled">
                                {{ __('general.reset') }}
                            </button>
                        </div>
                        <div>
                            <label class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                <span class="label-text px-2">{{ __('general.international') }}</span>
                                <input type="radio" class="radio radio-xs mx-1.5" value="{{ App\Models\Conference::SCOPE_INTERNATIONAL }}" wire:model.live="filter.scope.value" />
                            </label>
                        </div>
                        <div>
                            <label class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                <span class="label-text px-2">{{ __('general.national') }}</span>
                                <input type="radio" class="radio radio-xs mx-1.5" value="{{ App\Models\Conference::SCOPE_NATIONAL }}" wire:model.live="filter.scope.value" />
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-span-full sm:col-span-5 md:col-span-2 dropdown h-fit w-full" x-data="{ open: false }">
                    <button tabindex="0" role="button" class="btn btn-sm btn-outline border-gray-300 w-full" x-ref="button" @@click="open = ! open">
                        {{ __('general.state') }} <x-heroicon-o-chevron-down class="h-4 w-4" />
                    </button>

                    <div tabindex="0" 
                        class="mt-2 p-2 max-w-fit grid bg-white border rounded z-[1] shadow-xl" 
                        x-show="open" 
                        x-on:click.outside="open = false;"
                        x-on:mouseleave="open = false" 
                        x-anchor="$refs.button">
                        <div>
                            <button class="mb-2 btn btn-xs btn-outline no-animation border-neutral-300 w-full" wire:click="resetFilter('state')" wire:loading.attr="disabled">
                                {{ __('general.reset') }}
                            </button>
                        </div>
                        <div>
                            <label class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                <span class="label-text px-2">{{ __('general.current') }}</span>
                                <input type="checkbox" class="checkbox checkbox-xs mx-1.5" value="{{ self::STATE_CURRENT }}" wire:model.live="filter.state.value" />
                            </label>
                        </div>
                        <div>
                            <label class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                <span class="label-text px-2">{{ __('general.incoming') }}</span>
                                <input type="checkbox" class="checkbox checkbox-xs mx-1.5" value="{{ self::STATE_INCOMING }}" wire:model.live="filter.state.value" />
                            </label>
                        </div>
                        <div>
                            <label class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                <span class="label-text px-2">{{ __('general.archived') }}</span>
                                <input type="checkbox" class="checkbox checkbox-xs mx-1.5" value="{{ self::STATE_ARCHIVED }}" wire:model.live="filter.state.value" />
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-span-full sm:col-span-5 md:col-span-2 h-fit w-full" x-data="{ open: false }">
                    <button tabindex="0" role="button" class="btn btn-sm btn-outline border-gray-300 w-full" x-ref="button" @@click="open = ! open">
                        {{ __('general.topic') }} <x-heroicon-o-chevron-down class="h-4 w-4" />
                    </button>

                    <div tabindex="0" 
                        class="mt-2 p-2 max-w-fit grid bg-white border rounded z-[1] shadow-xl" 
                        x-show="open" 
                        x-on:click.outside="open = false;"
                        x-on:mouseleave="open = false" 
                        x-anchor="$refs.button">
                        <div>
                            <label class="mb-2 input input-xs w-full input-bordered !outline-none bg-white flex items-center">
                                <input type="search" class="grow" placeholder="{{ __('general.search') }}" wire:model.live.debounce="filter.topic.search" />
                                <x-heroicon-m-magnifying-glass class="h-3 w-3 opacity-70" />
                            </label>
                            <button class="mb-2 btn btn-xs btn-outline no-animation border-neutral-300 w-full" wire:click="resetFilter('topic')" wire:loading.attr="disabled">
                                {{ __('general.reset') }}
                            </button>
                        </div>

                        @if($topics->isNotEmpty())
                        <div class="max-h-64 overflow-scroll">
                            @foreach ($topics as $topic)
                                <div>
                                    <label class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                        <span class="label-text text-sm">{{ $topic->name }}</span>
                                        <input type="checkbox" class="checkbox checkbox-xs mx-1.5" value="{{ $topic->name }}" wire:model.live="filter.topic.value" />
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @else
                        <div>
                            <p class="text-center text-xs">
                                {{ __('general.option_are_empty') }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="col-span-full sm:col-span-5 md:col-span-2 dropdown h-fit w-full" x-data="{ open: false }">
                    <button tabindex="0" role="button" class="btn btn-sm btn-outline border-gray-300 w-full" x-ref="button" @@click="open = ! open">
                        {{ __('general.coordinator') }} <x-heroicon-o-chevron-down class="h-4 w-4" />
                    </button>

                    <div tabindex="0" 
                        class="mt-2 p-2 max-w-fit grid bg-white border rounded z-[1] shadow-xl" 
                        x-show="open" 
                        x-on:click.outside="open = false;"
                        x-on:mouseleave="open = false" 
                        x-anchor="$refs.button">
                        <div>
                            <label class="mb-2 input input-xs input-bordered !outline-none bg-white flex items-center">
                                <input type="search" class="grow" placeholder="{{ __('general.search') }}" wire:model.live.debounce="filter.coordinator.search" />
                                <x-heroicon-m-magnifying-glass class="h-3 w-3 opacity-70" />
                            </label>
                            <button class="mb-2 btn btn-xs btn-outline no-animation border-neutral-300 w-full" wire:click="resetFilter('coordinator')" wire:loading.attr="disabled">
                                {{ __('general.reset') }}
                            </button>
                        </div>
                        @if ($coordinatorList->isNotEmpty())
                            <div class="max-h-64 overflow-scroll">
                            @foreach ($coordinatorList as $coordinator)
                                <div>
                                    <label class="py-1.5 label cursor-pointer hover:bg-neutral-200 hover:!text-white transition-colors rounded">
                                        <span class="label-text px-2">{{ $coordinator->value }}</span>
                                        <input type="checkbox" class="checkbox checkbox-xs mx-1.5" value="{{ $coordinator->value }}" wire:model.live="filter.coordinator.value" />
                                    </label>
                                </div>
                            @endforeach
                            </div>
                        @else
                            <div>
                                <p class="text-center text-xs">
                                    {{ __('general.option_are_empty') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <button class="col-span-full md:col-span-2 btn btn-sm btn-primary w-full tooltip" data-tip="Clear all the filter and the search input." wire:click="resetFilters" wire:loading.attr="disabled">
                    {{ __('general.reset_all') }}
                </button>

                <div class="col-span-full w-full">

                    @if ($scopeSelected)
                        <span class="px-3 py-0.5 badge badge-primary text-xs">
                            {{ __('general.scope') }}: {{ Str::headline($scopeSelected) }}
                            <span class="ml-2">
                                <x-heroicon-o-x-mark class="h-3 w-3 cursor-pointer hover:text-neutral" wire:click="resetFilter('scope')" />
                            </span>
                        </span>
                    @endif

                    @if (!empty($stateSelected))
                        <span class="px-3 py-0.5 badge badge-primary text-xs">
                            {{ __('general.state') }}: {{ implode(', ', $stateSelected) }}
                            <span class="ml-2">
                                <x-heroicon-o-x-mark class="h-3 w-3 cursor-pointer hover:text-neutral" wire:click="resetFilter('state')" />
                            </span>
                        </span>
                    @endif

                    @if (!empty($topicSelected))
                        <span class="px-3 py-0.5 badge badge-primary text-xs">
                            {{ __('general.topic') }}: {{ implode(', ', $topicSelected) }}
                            <span class="ml-2">
                                <x-heroicon-o-x-mark class="h-3 w-3 cursor-pointer hover:text-neutral" wire:click="resetFilter('topic')" />
                            </span>
                        </span>
                    @endif

                    @if (!empty($coordinatorSelected))
                        <span class="px-3 py-0.5 badge badge-primary text-xs">
                            {{ __('general.coordinator') }}: {{ implode(', ', $coordinatorSelected) }}
                            <span class="ml-2">
                                <x-heroicon-o-x-mark class="h-3 w-3 cursor-pointer hover:text-neutral" wire:click="resetFilter('coordinator')" />
                            </span>
                        </span>
                    @endif

                </div>
            </div>

            <hr class="!my-5">
            <div class="space-y-4 conference-current">
                @if ($conferences->isNotEmpty())
                    <div class="grid gap-6 xl:grid-cols-2">
                        @foreach ($conferences as $conference)
                            <div class="gap-4 conference sm:flex">
                                @if ($conference->hasThumbnail())
                                    <div class="cover max-w-40">
                                        <img src="{{ $conference->getThumbnailUrl() }}" alt="{{ $conference->name }}">
                                    </div>
                                @endif
                                <div class="flex-1 space-y-2 information">
                                    <h3>
                                        <a href="{{ $conference->getHomeUrl() }}"
                                            class="font-bold conference-name link link-primary link-hover">{{ $conference->name }}</a>
                                    </h3>

                                    @if ($conference->getMeta('summary'))
                                        <div class="conference-summary user-content">
                                            {!! $conference->getMeta('summary') !!}
                                        </div>
                                    @endif
                                    <div class="flex items-center gap-2 text-sm">
                                        <a href="{{ $conference->getHomeUrl() }}" class="link link-primary">{{ __('general.view_conference') }}</a>
                                        @if($conference->currentScheduledConference)
                                            <a href="{{ $conference->currentScheduledConference->getHomeUrl() }}" class="link link-primary">{{ __('general.view_current_event') }}</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="my-12 text-center">
                        <p class="text-lg font-bold">{{ __('general.there_are_no_conferences_taking_place_at_this_time') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-website::layouts.main>
