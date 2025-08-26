<x-filament::dropdown
    placement="bottom-start"
    teleport
    class="-mx-2"
    id="switch-conference"
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
                              content: 'Administration',
                              placement: document.dir === 'rtl' ? 'left' : 'right',
                              theme: $store.theme,
                          }
                "
                x-tooltip.html="tooltip"
            @endif
            type="button"
            class="flex items-center justify-center w-full p-2 text-sm font-medium transition duration-75 rounded-lg outline-none fi-tenant-menu-trigger group gap-x-3 hover:bg-gray-100 focus:bg-gray-100 dark:hover:bg-white/5 dark:focus:bg-white/5"
        >
            <span
                @if (filament()->isSidebarCollapsibleOnDesktop())
                    x-show="$store.sidebar.isOpen"
                @endif
                class="grid justify-items-start text-start me-auto"
            >
                <span class="text-gray-950 dark:text-white">
                   {{ __('general.administration') }}
                </span>
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
            {{ __('general.switch_conference') }}
        </div>
        <div class="overflow-y-scroll max-h-64">
        @foreach (App\Models\Conference::with(['media'])->latest()->get() as $conference)
            <x-filament::dropdown.list.item
                :href="$conference->getPanelUrl()"
                :icon="filament()->getTenantAvatarUrl($conference)"
                tag="a"
            >
                {{ $conference->getLocalizedMeta('name') }}
            </x-filament::dropdown.list.item>
        @endforeach
        </div>
    </x-filament::dropdown.list>

</x-filament::dropdown>
