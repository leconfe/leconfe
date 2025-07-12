<x-filament-widgets::widget>

    @if($scheduledConference->isRegistrationOpen())
        @if(!$isRegiteredAsParticipant)
            <x-filament::section>
                <x-slot name="heading">
                    Welcome to the {{ $scheduledConference->title }}.
                </x-slot>   
                <p class="text-gray-600">
                    Registration is open now. If you haven't registered as a participant yet. You can <a href="{{ $registerAsParticipantUrl }}" class="text-primary-600 font-medium">register as participant here</a>.
                </p>
            </x-filament::section>
        @else
            <div>
                {{ $this->infolist }}

                <x-filament-actions::modals />
            </div>
        @endif
    @elseif($scheduledConference->isRegistrationNotYetOpen())
        <x-filament::section>
            <x-slot name="heading">
                Welcome to the {{ $scheduledConference->title }}.
            </x-slot>   
            <p class="text-gray-600">
                Registration is not yet open. It will open on {{ Date::parse($scheduledConference->getMeta('registration_start'))->format(Setting::get('format_date')) }}.
            </p>
        </x-filament::section>
    @elseif($scheduledConference->isRegistrationClosed())
        <x-filament::section>
            <x-slot name="heading">
                Welcome to the {{ $scheduledConference->title }}.
            </x-slot>   
            <p class="text-gray-600">
                Registration is already closed.
            </p>
        </x-filament::section>
    @endif


</x-filament-widgets::widget>