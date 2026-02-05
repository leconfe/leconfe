<x-filament-panels::page class="flex flex-col gap-y-6" x-data="{ activeTab: 'data-tab' }" x-cloak>
        <x-filament::tabs class="">
            <x-filament::tabs.item
                alpine-active="activeTab === 'data-tab'"
                x-on:click="activeTab = 'data-tab'"
                >
                {{ __('general.proceeding_data') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item
                alpine-active="activeTab === 'articles-tab'"
                x-on:click="activeTab = 'articles-tab'"
                >
                {{ __('general.articles') }}
            </x-filament::tabs.item>
        </x-filament::tabs>
        <div x-show="activeTab === 'data-tab'">
            <form wire:submit='submit' class="space-y-4">
                <div class="p-4 bg-white rounded-xl ring-1 ring-gray-950/5">
                    {{ $this->form }}
                </div>

                @can('update', $this->getRecord())
                    <x-filament::button type="submit" icon="iconpark-save-o" wire:target="submit">
                        {{ __('general.save') }}
                    </x-filament::button>
                @endcan
            </form>
        </div>
        <div x-show="activeTab === 'articles-tab'">
            {{ $this->table }}
        </div>
</x-filament-panels::page>
