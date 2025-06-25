<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-4 max-w-3xl">
        @if ($coverImageUrl)
            <img src="{{ $coverImageUrl }}" alt="cover form" class="rounded-xl ring-1 ring-gray-950/5">
        @endif
        @if ($registrationFormHeader)
            <x-filament::section>
                {{ $registrationFormHeader }}
            </x-filament::section>
        @endif

        {{ $this->form }}

        <x-filament::button type="submit">
            {{ __('general.save') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
