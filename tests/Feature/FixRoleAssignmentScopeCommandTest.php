<?php

namespace Tests\Feature;

use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FixRoleAssignmentScopeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fixes_mismatched_role_assignment_scope(): void
    {
        $role = Role::withoutGlobalScopes()->create([
            'name' => UserRole::ConferenceManager->value,
            'guard_name' => 'web',
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
        ]);

        $user = User::create([
            'given_name' => 'Role',
            'family_name' => 'Fix',
            'email' => 'role-fix@example.test',
            'password' => 'password123456',
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $role->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 106,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        $this->artisan('roles:fix-assignment-scope')
            ->expectsOutput('Found 1 mismatched assignments: 1 update(s), 0 duplicate delete(s).')
            ->expectsOutput('Role assignment scopes fixed successfully.')
            ->assertSuccessful();

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        $this->assertDatabaseMissing('model_has_roles', [
            'role_id' => $role->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 106,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
    }

    public function test_command_deletes_duplicate_mismatched_assignment_when_correct_row_already_exists(): void
    {
        $role = Role::withoutGlobalScopes()->create([
            'name' => UserRole::ConferenceManager->value,
            'guard_name' => 'web',
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
        ]);

        $user = User::create([
            'given_name' => 'Role',
            'family_name' => 'Duplicate',
            'email' => 'role-duplicate@example.test',
            'password' => 'password123456',
        ]);

        DB::table('model_has_roles')->insert([
            [
                'role_id' => $role->getKey(),
                'conference_id' => 103,
                'scheduled_conference_id' => 106,
                'model_type' => User::class,
                'model_id' => $user->getKey(),
            ],
            [
                'role_id' => $role->getKey(),
                'conference_id' => 103,
                'scheduled_conference_id' => 0,
                'model_type' => User::class,
                'model_id' => $user->getKey(),
            ],
        ]);

        $this->artisan('roles:fix-assignment-scope')
            ->expectsOutput('Found 1 mismatched assignments: 0 update(s), 1 duplicate delete(s).')
            ->expectsOutput('Role assignment scopes fixed successfully.')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('model_has_roles')
            ->where('role_id', $role->getKey())
            ->where('model_type', User::class)
            ->where('model_id', $user->getKey())
            ->count());

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
    }

    public function test_command_only_processes_roles_registered_at_conference_scope(): void
    {
        $conferenceRole = Role::withoutGlobalScopes()->create([
            'name' => UserRole::ConferenceManager->value,
            'guard_name' => 'web',
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
        ]);

        $scheduledRole = Role::withoutGlobalScopes()->create([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => 103,
            'scheduled_conference_id' => 106,
        ]);

        $user = User::create([
            'given_name' => 'Role',
            'family_name' => 'Scoped',
            'email' => 'role-scoped@example.test',
            'password' => 'password123456',
        ]);

        DB::table('model_has_roles')->insert([
            [
                'role_id' => $conferenceRole->getKey(),
                'conference_id' => 103,
                'scheduled_conference_id' => 106,
                'model_type' => User::class,
                'model_id' => $user->getKey(),
            ],
            [
                'role_id' => $scheduledRole->getKey(),
                'conference_id' => 103,
                'scheduled_conference_id' => 0,
                'model_type' => User::class,
                'model_id' => $user->getKey(),
            ],
        ]);

        $this->artisan('roles:fix-assignment-scope')
            ->expectsOutput('Found 1 mismatched assignments: 1 update(s), 0 duplicate delete(s).')
            ->expectsOutput('Role assignment scopes fixed successfully.')
            ->assertSuccessful();

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $conferenceRole->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $scheduledRole->getKey(),
            'conference_id' => 103,
            'scheduled_conference_id' => 0,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
    }
}
