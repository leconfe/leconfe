<div>
    <x-filament::section>
    {{ $this->infolist }}

        <form wire:submit='submit'>
            <div class="mt-5 space-y-4">
                {{ $this->form }}
                @if (auth()->user()->can('editing', $submission) || auth()->user()->can('updatePublicationDate', $submission))
                    <x-filament::button type="submit" icon="iconpark-save-o">
                        {{ __('general.submit') }}
                    </x-filament::button>
                @endif
            </div>
        </form>

        <x-filament-actions::modals />
    </x-filament::section>
</div>
