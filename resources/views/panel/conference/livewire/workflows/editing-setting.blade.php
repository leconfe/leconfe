<div class="space-y-6">
    <div class="flex items-center">
        <div class="flex space-x-3 justify-center items-center">
            <h3 class="text-xl font-semibold leading-6 text-gray-950 dark:text-white">
               {{__('translation.editingSetting.editingSettingH3Editing')}}
            </h3>
            @if($this->isStageOpen())
                <x-filament::badge color="success">{{ __('translation.button.open') }}</x-filament::badge>
            @else
                <x-filament::badge color="warning">{{ __('translation.button.close') }}</x-filament::badge>
            @endif
        </div>
        @livewire(App\Panel\Conference\Livewire\Workflows\Components\StageSchedule::class, ['stage' => $this->getStage()])
    </div>
    <div>
        <form wire:submit='save' class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit" color="primary" icon='lineawesome-save-solid'>
                {{ __('Save') }}
            </x-filament::button>
        </form>
    </div>
</div>
