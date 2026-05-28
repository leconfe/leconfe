<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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

    public function test_admin_can_view_draft_scheduled_conference_in_scheduled_context(): void
    {
        $conference = Conference::create([
            'name' => 'Demo Conference',
            'path' => 'demo',
        ]);

        $draft = ScheduledConference::create([
            'conference_id' => $conference->getKey(),
            'title' => 'Draft Scheduled Conference',
            'path' => 'draft',
            'date_start' => now(),
            'date_end' => now()->addDays(3),
            'is_published' => false,
        ]);

        $adminRole = Role::withoutGlobalScopes()->create([
            'name' => UserRole::Admin->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]);

        $admin = User::create([
            'given_name' => 'Admin',
            'family_name' => 'User',
            'email' => 'admin@example.test',
            'password' => 'password123456',
        ]);
        $admin->assignRole($adminRole);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($draft->getKey());

        $this->assertTrue($admin->fresh()->hasRole(UserRole::Admin->value));
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('view', $draft));
    }

    public function test_admin_is_listed_in_scheduled_conference_user_resource(): void
    {
        $conference = Conference::create([
            'name' => 'Demo Conference',
            'path' => 'demo',
        ]);

        $draft = ScheduledConference::create([
            'conference_id' => $conference->getKey(),
            'title' => 'Draft Scheduled Conference',
            'path' => 'draft',
            'date_start' => now(),
            'date_end' => now()->addDays(3),
            'is_published' => false,
        ]);

        Role::withoutGlobalScopes()->create([
            'name' => UserRole::Admin->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]);

        Role::withoutGlobalScopes()->create([
            'name' => UserRole::Author->value,
            'guard_name' => 'web',
            'conference_id' => 999,
            'scheduled_conference_id' => 0,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $foreignUser = User::factory()->create([
            'email' => 'foreign@example.test',
        ]);
        app()->setCurrentConferenceId(999);
        app()->setCurrentScheduledConferenceId(null);
        $foreignUser->assignRole(UserRole::Author->value);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($draft->getKey());
        $this->actingAs($admin);

        $listedUserIds = UserResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($listedUserIds->contains($admin->getKey()));
        $this->assertFalse($listedUserIds->contains($foreignUser->getKey()));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }
}
