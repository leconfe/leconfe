<?php

namespace App\Utils\UpgradeSchemas;

use App\Actions\ScheduledConferences\ScheduledConferenceRegisterEntityAction;
use App\Models\ScheduledConference;
use App\Models\User;

class Upgrade130Rc1 extends UpgradeBase
{
    public function run(): void
    {
        $this->registerScheduledConferences();
        $this->updateNotificationUserPreferences();
    }

    public function registerScheduledConferences()
    {
        $scheduledConferences = ScheduledConference::query()
            ->with(['conference'])
            ->withoutGlobalScopes()
            ->get();

        foreach ($scheduledConferences as $sc) {
            ScheduledConferenceRegisterEntityAction::run($sc);
        }
    }

    public function updateNotificationUserPreferences()
    {
        User::query()
            ->lazy()
            ->each(fn(User $user) => $user->setMeta('enable_new_announcement_email', true));
    }
}
