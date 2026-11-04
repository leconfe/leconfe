<div>
    @if (count($this->contexts()))
        <form wire:submit="save" class="space-y-4">
            <x-filament::section>
                {{ $this->form }}
            </x-filament::section>

            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
                {{ __('general.save') }}
            </x-filament::button>
        </form>
    @else
        <x-filament::section>
            {{ __('email_preferences.empty') }}
        </x-filament::section>
    @endif
</div>
