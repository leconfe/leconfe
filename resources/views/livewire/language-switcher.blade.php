<div>
    <x-filament::dropdown.header icon="heroicon-m-language">
        {{ __('general.interface_language') }}
    </x-filament::dropdown.header>

    <x-filament::dropdown.list>
        @foreach ($languages as $locale => $language)
            <x-filament::dropdown.list.item :icon="$locale === app()->getLocale() ? 'heroicon-o-check' : ''" tag="button" wire:click="switchLanguage('{{ $locale }}')">
                {{ $language }}
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</div>
