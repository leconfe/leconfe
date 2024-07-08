<x-filament::section heading="References">
    <form wire:submit='submit' class="space-y-4">
        {{ $this->form }}
        @can('editing', $submission)
            <x-filament::button type="submit" icon="iconpark-save-o">
                {{ __('translation.button.submit') }}
            </x-filament::button>
        @endcan
    </form>
</x-filament::section>