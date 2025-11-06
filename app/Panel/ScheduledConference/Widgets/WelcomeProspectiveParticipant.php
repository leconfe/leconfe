<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Models\Enums\UserRole;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Widgets\Widget;

class WelcomeProspectiveParticipant extends Widget
{
    protected static string $view = 'panel.scheduledConference.widgets.welcome-prospective-participant';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user->roles->isEmpty() || $user->cannot('update', app()->getCurrentScheduledConference());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'scheduledConference' => app()->getCurrentScheduledConference(),
            'submissionUrl' => SubmissionResource::getUrl(),
            'participantRegistrationUrl' => ParticipantRegistration::getUrl(),
            'participantPaymentUrl' => PaymentDetail::getUrl(),
        ];
    }
}
