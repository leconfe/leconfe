<x-filament-panels::page>
    @if (! auth()->user()->can('Administration:view'))
        <x-filament::section
            :heading="__('general.my_scheduled_conferences')"
            :description="__('general.my_scheduled_conferences_description')"
        >
            @if ($this->scheduledConferencePortals->isEmpty())
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('general.no_scheduled_conference_roles_description') }}
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->scheduledConferencePortals as $portal)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div>
                                <p class="text-xs text-gray-500">{{ $portal['conference_name'] }}</p>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $portal['title'] }}</h3>
                            </div>

                            @if ($portal['date_range'])
                                <p class="mt-2 text-sm text-gray-500">{{ $portal['date_range'] }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($portal['roles'] as $role)
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $role }}
                                    </x-filament::badge>
                                @endforeach
                            </div>

                            <div class="mt-4">
                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    :href="$portal['panel_url']"
                                >
                                    {{ __('general.open_scheduled_conference') }}
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif

    @if (auth()->user()->can('Administration:view'))
        <div class="mt-6">
            {{ $this->infolist }}
        </div>
    @endif
</x-filament-panels::page>
