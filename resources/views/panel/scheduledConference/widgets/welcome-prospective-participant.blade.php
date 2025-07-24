<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('scheduled_conference.welcome_message_title', ['conference' => $scheduledConference->title]) }}
        </x-slot>
        <p class="text-gray-600">
            {!! __('scheduled_conference.welcome_message_submit_paper', ['submit_link' => $submissionUrl]) !!}
        </p>
        @if(auth()->user()->isRegisteredAsParticipant())
            <p class="text-gray-600">
                {!! __('scheduled_conference.welcome_message_registered_non_presenter', ['participant_detail_link' => $participantPaymentUrl]) !!}
            </p>
        @else
            <p class="text-gray-600">
                {!! __('scheduled_conference.welcome_message_non_presenter', ['register_link' => $participantRegistrationUrl]) !!}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
