<x-filament-panels::page>
    @if (! $isOpen)
        <div class="max-w-3xl rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-gray-800 dark:text-yellow-300" role="alert">
            {{ $closedMessage ?? __('general.registration_closed') }}
        </div>
    @else
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
    @endif
</x-filament-panels::page>
