<div class="grid auto-cols-fr gap-y-2 p-4">
    <x-filament::input.wrapper
         
    >
        <x-filament::input
            type="text"
            disabled
            helper-text="Laboris est voluptate aliquip reprehenderit aliqua."
        />
    </x-filament::input.wrapper>
    @if (filled($description))
        <x-filament-forms::field-wrapper.helper-text>
            {{ $description }}
        </x-filament-forms::field-wrapper.helper-text>
    @endif
</div>