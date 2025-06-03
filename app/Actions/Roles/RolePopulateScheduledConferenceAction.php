<?php

namespace App\Actions\Roles;

use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\ScheduledConference;
use Lorisleiva\Actions\Concerns\AsAction;

class RolePopulateScheduledConferenceAction
{
    use AsAction;

    public function handle(ScheduledConference $scheduledConference)
    {
        foreach (UserRole::scheduledConferenceRoles() as $role) {
            $role = Role::withoutGlobalScopes()->firstOrCreate([
                'name' => $role->value,
                'scheduled_conference_id' => $scheduledConference->getKey(),
            ]);
        }
    }
}
