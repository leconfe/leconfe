<?php

namespace Tests\Unit;

use App\Models\Enums\UserRole;
use App\Models\Role;
use Tests\TestCase;

class RoleDefaultPermissionsTest extends TestCase
{
    public function test_scheduled_conference_editor_can_login_as_users(): void
    {
        $this->assertContains(
            'User:loginAs',
            Role::getPermissionsForRole(UserRole::ScheduledConferenceEditor->value)
        );
    }
}
