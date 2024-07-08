<form wire:submit="submit">
    <div class="space-y-6">
        {{ $this->form }}
        <div class="flex items-center justify-between mt-4">
            <div>
                <x-filament::button icon="heroicon-o-chevron-left" x-show="! isFirstStep()" x-cloak x-on:click="previousStep"
                    color="secondary" size="sm">
                    {{ __('translation.button.previous') }}
                </x-filament::button>
            </div>
    
            <div>
                {{ $this->nextStep() }}
                {{-- <x-filament::button type="submit" icon="heroicon-o-chevron-right" icon-position="after" x-show="! isLastStep()" x-cloak
                    wire:loading.class.delay="opacity-70 cursor-wait" size="sm">
                    Next
                </x-filament::button> --}}
            </div>
        </div>
    </div>
</form>
