<?php

namespace Tests\Feature;

use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_conference_role_stays_in_conference_scope_when_assigned_from_scheduled_context(): void
    {
        app()->setCurrentConferenceId(103);
        app()->setCurrentScheduledConferenceId(106);

        $conferenceRole = Role::withoutGlobalScopes()->create([
            'name' => UserRole::ConferenceManager->value,
            'guard_name' => 'web',
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
        ]);

        $user = User::create([
            'given_name' => 'Scope',
            'family_name' => 'Tester',
            'email' => 'scope@example.test',
            'password' => 'password123456',
        ]);

        $user->assignRole(UserRole::ConferenceManager->value);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $conferenceRole->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        $this->assertDatabaseMissing('model_has_roles', [
            'role_id' => $conferenceRole->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 106,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
    }

    public function test_role_scope_does_not_leak_from_other_conferences(): void
    {
        app()->setCurrentConferenceId(103);
        app()->setCurrentScheduledConferenceId(106);

        $allowedRole = Role::withoutGlobalScopes()->create([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => 103,
            'scheduled_conference_id' => 106,
        ]);

        $foreignRole = Role::withoutGlobalScopes()->create([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => 999,
            'scheduled_conference_id' => 106,
        ]);

        $scopedRoleIds = Role::query()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->pluck('id');

        $this->assertTrue($scopedRoleIds->contains($allowedRole->getKey()));
        $this->assertFalse($scopedRoleIds->contains($foreignRole->getKey()));
    }

    public function test_admin_role_is_available_in_scheduled_conference_scope(): void
    {
        Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Admin->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]);

        $user = User::create([
            'given_name' => 'Admin',
            'family_name' => 'User',
            'email' => 'admin-scope@example.test',
            'password' => 'password123456',
        ]);

        $user->assignRole(UserRole::Admin->value);

        app()->setCurrentConferenceId(103);
        app()->setCurrentScheduledConferenceId(106);

        $this->assertTrue($user->fresh()->hasRole(UserRole::Admin));
        $this->assertTrue($user->fresh()->hasPermissionTo('ScheduledConference:update'));
    }
}
