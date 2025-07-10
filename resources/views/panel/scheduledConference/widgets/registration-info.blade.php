<x-filament-widgets::widget>
    @if(!$isRegiteredAsParticipant)
        <x-filament::section>
            <x-slot name="heading">
                Welcome to the {{ $conferenceTitle }}.
            </x-slot>   
            <p class="text-gray-600">
                If you haven't registered as a participant yet. You can <a href="{{ $registerAsParticipantUrl }}" class="text-primary-600 font-medium">register as participant here</a>.
            </p>
        </x-filament::section>
    @else
        <div>
            {{ $this->infolist }}

            <x-filament-actions::modals />
        </div>
    @endif
</x-filament-widgets::widget>