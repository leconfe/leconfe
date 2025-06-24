<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Panel\ScheduledConference\Pages\ParticipantRegister;
use Filament\Widgets\Widget;

class WelcomeParticipant extends Widget
{
    protected static string $view = 'panel.scheduledConference.widgets.welcome-participant';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'isRegiteredAsParticipant' => $user->isRegisteredAsParticipant(),
            'registerAsParticipantUrl' => ParticipantRegister::getUrl(),
            'conferenceTitle' => app()->getCurrentScheduledConference()?->title,
        ];
    }
}
