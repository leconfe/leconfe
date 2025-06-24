<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Welcome to the {{ $conferenceTitle }}.
        </x-slot>   
        @if(!$isRegiteredAsParticipant)
            <p class="text-gray-600">
                If you haven't registered as a participant yet. You can <a href="{{ $registerAsParticipantUrl }}" class="text-primary-600 font-medium">register as participant here</a>.
            </p>
        @else
            <p class="text-gray-600">
                You are registered as a participant in this conference. You can view the <a href="#" class="text-primary-600 font-medium">details here</a>.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>