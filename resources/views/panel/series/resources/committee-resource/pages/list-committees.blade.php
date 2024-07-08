<x-filament-panels::page @class([
    'fi-resource-list-records-page',
    'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
])>
    <div class="flex flex-col gap-y-6" x-data="{ activeTab: 'participant-table' }">
        <x-filament::tabs>
            <x-filament::tabs.item alpine-active="activeTab === 'participant-table'" x-on:click="activeTab = 'participant-table'">
               {{ __('translation.committeResource.listCommittesTabsCommittees') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item alpine-active="activeTab === 'role-table'"
                x-on:click="activeTab = 'role-table'">
                {{ __('translation.committeResource.listCommittesTabsCommitteePositions') }}
            </x-filament::tabs.item>

        </x-filament::tabs>

        <div x-show="activeTab === 'participant-table'">
            @if (count($tabs = $this->getTabs()))
                <x-filament::tabs>
                    {{ \Filament\Support\Facades\FilamentView::renderHook('panels::resource.pages.list-records.tabs.start', scopes: $this->getRenderHookScopes()) }}

                    @foreach ($tabs as $tabKey => $tab)
                        @php
                            $activeTab = strval($activeTab);
                            $tabKey = strval($tabKey);
                        @endphp

                        <x-filament::tabs.item :active="$activeTab === $tabKey" :badge="$tab->getBadge()" :icon="$tab->getIcon()" :icon-position="$tab->getIconPosition()"
                            :wire:click="'$set(\'activeTab\', ' . (filled($tabKey) ? ('\'' . $tabKey . '\'') : 'null') . ')'">
                            {{ $tab->getLabel() ?? $this->generateTabLabel($tabKey) }}
                        </x-filament::tabs.item>
                    @endforeach

                    {{ \Filament\Support\Facades\FilamentView::renderHook('panels::resource.pages.list-records.tabs.end', scopes: $this->getRenderHookScopes()) }}
                </x-filament::tabs>
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook('panels::resource.pages.list-records.table.before', scopes: $this->getRenderHookScopes()) }}

            {{ $this->table }}

            {{ \Filament\Support\Facades\FilamentView::renderHook('panels::resource.pages.list-records.table.after', scopes: $this->getRenderHookScopes()) }}
        </div>
        <div x-show="activeTab === 'role-table'" style="display: none">
            @livewire(App\Panel\Series\Livewire\Tables\CommitteeRoleTable::class, ['lazy' => true])
        </div>
    </div>
</x-filament-panels::page>
