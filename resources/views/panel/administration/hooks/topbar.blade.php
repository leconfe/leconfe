@use('\App\Models\Conference')
<x-filament::dropdown
    placement="bottom-start"
    teleport
    id="switch-conference"
    >
    <x-slot name="trigger">
        <button
            @if (filament()->isSidebarCollapsibleOnDesktop())
                x-data="{ tooltip: false }"
                x-effect="
                    tooltip = $store.sidebar.isOpen
                        ? false
                        : {
                            content: 'Administration',
                            placement: document.dir === 'rtl' ? 'left' : 'right',
                            theme: $store.theme,
                        }
                "
                x-tooltip.html="tooltip"
            @endif
            type="button"
            class="fi-tenant-menu-trigger group flex w-full items-center justify-center gap-x-3 rounded-lg p-2 text-sm font-medium outline-none transition duration-75 hover:bg-gray-100 focus:bg-gray-100 dark:hover:bg-white/5 dark:focus:bg-white/5">
            <x-filament::icon icon="heroicon-m-cog-8-tooth" class="h-5 w-5" />
            <span class="hidden md:grid justify-items-start text-start text-gray-950 dark:text-white text-lg">
                Administrations
            </span>
            <x-filament::icon icon="heroicon-m-chevron-down" icon-alias="panels::tenant-menu.toggle-button"
                :x-show="filament()->isSidebarCollapsibleOnDesktop() ? '$store.sidebar.isOpen' : null"
                class="ms-auto h-5 w-5 shrink-0 text-gray-400 transition duration-75 group-hover:text-gray-500 group-focus:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400 dark:group-focus:text-gray-400" />
        </button>
    </x-slot>
    <x-filament::dropdown.list>
        @foreach (Conference::all() as $conference)
            <x-filament::dropdown.list.item
                :href="$conference->getPanelUrl()"
                :icon="filament()->getTenantAvatarUrl($conference)"
                tag="a"
            >
                {{ $conference->getLocalizedMeta('name') }}
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
