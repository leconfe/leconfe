<x-filament::dropdown
    placement="bottom-start"
    teleport
    class="-mx-2"
    id="switch-scheduled-conference"
    width="xs"

>
    <x-slot name="trigger">
        <button
            @if (filament()->isSidebarCollapsibleOnDesktop())
                x-data="{ tooltip: false }"
                x-effect="
                    tooltip = $store.sidebar.isOpen
                        ? false
                        : {
                              content: @js($currentScheduledConference->title),
                              placement: document.dir === 'rtl' ? 'left' : 'right',
                              theme: $store.theme,
                          }
                "
                x-tooltip.html="tooltip"
            @endif
            type="button"
            class="flex items-center justify-between w-full p-2 text-sm font-medium transition duration-75 rounded-lg outline-none fi-tenant-menu-trigger group gap-x-3 hover:bg-gray-100 focus:bg-gray-100 dark:hover:bg-white/5 dark:focus:bg-white/5"
        >
            <span
                @if (filament()->isSidebarCollapsibleOnDesktop())
                    x-show="$store.sidebar.isOpen"
                @endif
                class="flex flex-wrap items-center justify-between truncate text-start grow"
            >
                <span class="text-gray-950 dark:text-white">
                    {{ $currentScheduledConference->title }}
                </span>

                @if($currentScheduledConference->current)
                <x-filament::badge size="sm" class="" color="primary">
                    {{ __('general.current') }}
                </x-filament::badge>
                @endif

                @hook('Panel::ScheduledConference::TenantMenuAfterCurrentTitle')
            </span>

             <x-filament::icon
                icon="heroicon-m-chevron-down"
                icon-alias="panels::tenant-menu.toggle-button"
                class="hidden w-5 h-5 text-gray-400 transition duration-75 md:block shrink-0 group-hover:text-gray-500 group-focus:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400 dark:group-focus:text-gray-400"
            />
        </button>
    </x-slot>

    <x-filament::dropdown.list>
        <div class="flex items-center w-full gap-2 p-2 text-sm font-medium transition-colors duration-75 border-b outline-none whitespace-nowrap">
            {{ __('general.switch_scheduled_conference') }}
        </div>

        @can('Administration:view')
            <x-filament::dropdown.list.item
                :href="route('filament.administration.home')"
                icon="heroicon-s-cog"
                tag="a"
            >
                {{ __('general.administration') }}
            </x-filament::dropdown.list.item>
        @endcan

        @can('view', $currentConference)
            <x-filament::dropdown.list.item
                :href="$currentConference->getPanelUrl()"
                icon="heroicon-m-arrow-uturn-left"
                tag="a"
            >
                {{ __('general.back_to_conference') }}
            </x-filament::dropdown.list.item>
        @endcan

        <div class="overflow-y-scroll max-h-64">
            @foreach ($scheduledConferences as $scheduledConference)
                @can('view', $scheduledConference)
                    <x-filament::dropdown.list.item
                        :href="$scheduledConference->getPanelUrl()"
                        :icon="filament()->getTenantAvatarUrl($scheduledConference)"
                        tag="a"
                        badge-color="primary"
                    >
                        {{ $scheduledConference->title }}
                        @if($scheduledConference->current)
                        <x-slot name="badge">
                            {{ __('general.current') }}
                        </x-slot>
                        @endif
                    </x-filament::dropdown.list.item>
                @endcan
            @endforeach
        </div>
    </x-filament::dropdown.list>

</x-filament::dropdown>
