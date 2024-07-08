<div class="sidebar-settings space-y-4">
    <x-filament::section>
        <x-slot name="heading">
            {{ __('translation.sideBarSetting.headingSidebarsManagers') }}
        </x-slot>
        @if ($sidebars->isEmpty())
            <div>
                <p class="text-sm text-gray-500">
                    {{ __('translation.sideBarSetting.emptyNoSidebarsFound') }}
                </p>
            </div>
        @else
            <div class="space-y-4" x-data="sidebarsManager({{ Js::from($sidebars) }})" wire:ignore>
                <div class="sidebar-items text-sm flex flex-col gap-2" x-ref="sortable">
                    <template x-for="sidebar in items" :key="sidebar.id">
                        <div class="sidebar-item" data-sortable-item :data-id="sidebar.id">
                            <div class="relative group">
                                <div class="flex items-center gap-2 rounded-xl border bg-white">
                                    <button type="button"
                                        class="text-sm p-3 bg-gray-50 rounded-l-xl text-gray-500 hover:text-gray-900 border-r"
                                        data-sortable-handle>
                                        <x-heroicon-s-arrows-up-down class="h-4 w-4" />
                                    </button>
                                    <div class="sidebar-item-name flex items-center gap-2">
                                        <div x-html="sidebar.prefixName"></div>
                                        <div x-text="sidebar.name"></div>
                                        <div x-html="sidebar.suffixName"></div>
                                    </div>
                                    <x-filament::input.checkbox class="ml-auto mx-4" x-model="sidebar.isActive" />
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <x-filament::button x-on:click="save" wire:target="save">
                    {{ __('translation.button.save') }}
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</div>
