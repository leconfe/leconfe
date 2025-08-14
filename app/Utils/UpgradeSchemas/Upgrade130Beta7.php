<?php

namespace App\Utils\UpgradeSchemas;

use App\Actions\ScheduledConferences\ScheduledConferenceRegisterEntityAction;
use App\Models\ScheduledConference;

class Upgrade130Beta7 extends UpgradeBase
{
    public function run(): void
    {
        $this->registerScheduledConferences();
    }

    public function registerScheduledConferences()
    {
        $scheduledConferences = ScheduledConference::query()
            ->withoutGlobalScopes()
            ->lazy();

        foreach ($scheduledConferences as $sc) {
            ScheduledConferenceRegisterEntityAction::dispatch($sc);
        }
    }
}
