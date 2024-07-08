<div>
    <form wire:submit='submit'>
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-4" icon="iconpark-saveone-o">
            {{ __('translation.button.submit') }}
        </x-filament::button>
    </form>
</div>