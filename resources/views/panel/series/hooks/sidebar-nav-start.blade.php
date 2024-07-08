@use('\App\Models\Serie')

@php
    $currentSerie = app()->getCurrentSerie();
    $currentConference = app()->getCurrentConference();
@endphp

<x-filament::dropdown
    placement="bottom-start"
    teleport
    class="-mx-2"
    id="switch-series"
>
    <x-slot name="trigger">
        <button
            @if (filament()->isSidebarCollapsibleOnDesktop())
                x-data="{ tooltip: false }"
                x-effect="
                    tooltip = $store.sidebar.isOpen
                        ? false
                        : {
                              content: @js($currentSerie->title),
                              placement: document.dir === 'rtl' ? 'left' : 'right',
                              theme: $store.theme,
                          }
                "
                x-tooltip.html="tooltip"
            @endif
            type="button"
            class="fi-tenant-menu-trigger group flex w-full items-center justify-between gap-x-3 rounded-lg p-2 text-sm font-medium outline-none transition duration-75 hover:bg-gray-100 focus:bg-gray-100 dark:hover:bg-white/5 dark:focus:bg-white/5"
        >
            <span
                @if (filament()->isSidebarCollapsibleOnDesktop())
                    x-show="$store.sidebar.isOpen"
                @endif
                class="flex flex-wrap items-center justify-between text-start truncate grow"
            >
                <span class="text-gray-950 dark:text-white">
                    {{ $currentSerie->title }}
                </span>

                @if($currentSerie->current)
                <x-filament::badge size="sm" class="" color="primary">
                    {{ __('translation.sidebarNavStart.current') }}
                </x-filament::badge>
                @endif
            </span>

             <x-filament::icon
                icon="heroicon-m-chevron-down"
                icon-alias="panels::tenant-menu.toggle-button"
                class="hidden md:block h-5 w-5 shrink-0 text-gray-400 transition duration-75 group-hover:text-gray-500 group-focus:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400 dark:group-focus:text-gray-400"
            />
        </button>
    </x-slot>

    <x-filament::dropdown.list>
        <div class="flex w-full items-center gap-2 whitespace-nowrap p-2 text-sm transition-colors duration-75 outline-none font-medium border-b">
            {{ __('translation.sidebarNavStart.switchSeries') }}
        </div>
        
        @can('Administration:view')
        <x-filament::dropdown.list.item
            :href="route('filament.administration.home')"
            icon="heroicon-s-cog"
            tag="a"
        >
            {{ __('Administration') }}
        </x-filament::dropdown.list.item>
        @endcan

        <x-filament::dropdown.list.item
            :href="$currentConference->getPanelUrl()"
            icon="heroicon-m-arrow-uturn-left"
            tag="a"
        >
        {{ __('translation.sidebarNavStart.backtoConference') }}
        </x-filament::dropdown.list.item>
        <div class="max-h-64 overflow-y-scroll border-t">
            @foreach (Serie::where('path', '!=', $currentSerie->path)->latest()->get() as $serie)
                <x-filament::dropdown.list.item
                    :href="$serie->getPanelUrl()"
                    :icon="filament()->getTenantAvatarUrl($serie)"
                    tag="a"
                    badge-color="primary"
                >
                    {{ $serie->title }}
                    @if($serie->current)
                    <x-slot name="badge">
                        {{ __('translation.sidebarNavStart.current') }}
                    </x-slot>
                    @endif
                </x-filament::dropdown.list.item>
            @endforeach
        </div>
    </x-filament::dropdown.list>

</x-filament::dropdown>