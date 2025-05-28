<div>
    <div x-data="{ activeTab: '{{ $locale }}' }" class="space-y-4">
        @if (count($languages) > 1)
            <x-filament::tabs label="Language Tabs">
                @foreach ($languages as $lang)
                    <x-filament::tabs.item 
                        alpine-active="activeTab === '{{ $lang }}'"
                        x-on:click="activeTab = '{{ $lang }}'"
                        >
                        {{ $this->getLocaleLabel($lang) }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        @endif

        @foreach ($this->getCachedForms() as $key => $form)
            <div x-show="activeTab === '{{ $key }}'" x-cloak wire:ignore.self>
                {{ $form }}
            </div>
        @endforeach
    </div>

    <x-filament-actions::modals />
</div>
