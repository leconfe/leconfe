<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ParticipantList;
use App\Panel\ScheduledConference\Pages\Presentations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
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

    public function test_scheduled_context_role_sync_preserves_conference_scoped_roles(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Scoped Conference',
            'path' => 'scoped-conference',
        ]);
        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Scoped Schedule',
            'path' => 'scoped-schedule',
        ]);

        $conferenceManagerRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::ConferenceManager->value,
            'guard_name' => 'web',
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => 0,
        ]);
        $scheduledEditorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
        ]);
        $trackEditorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::TrackEditor->value,
            'guard_name' => 'web',
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
        ]);

        $user = User::factory()->create([
            'email' => 'scoped-sync@example.test',
            'password' => Hash::make('password12345'),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $user->assignRole($conferenceManagerRole);
        $user->assignRole($scheduledEditorRole);

        $user->syncRoles([$trackEditorRole->name]);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $conferenceManagerRole->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => 0,
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
        $this->assertDatabaseMissing('model_has_roles', [
            'role_id' => $scheduledEditorRole->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $trackEditorRole->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
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

    public function test_assign_participant_user_query_excludes_editors_assigned_in_other_scheduled_conferences(): void
    {
        $currentConference = Conference::query()->create([
            'name' => 'Current Conference',
            'path' => 'current-conference',
        ]);
        $currentScheduledConference = ScheduledConference::query()->create([
            'conference_id' => $currentConference->getKey(),
            'title' => 'Current Scheduled Conference',
            'path' => 'current-scheduled-conference',
        ]);

        $foreignConference = Conference::query()->create([
            'name' => 'Foreign Conference',
            'path' => 'foreign-conference',
        ]);
        $foreignScheduledConference = ScheduledConference::query()->create([
            'conference_id' => $foreignConference->getKey(),
            'title' => 'Foreign Scheduled Conference',
            'path' => 'foreign-scheduled-conference',
        ]);

        $editorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => $currentConference->getKey(),
            'scheduled_conference_id' => $currentScheduledConference->getKey(),
        ]);
        Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Admin->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]);

        $currentEditor = User::factory()->create([
            'email' => 'current-editor@example.test',
            'password' => Hash::make('password12345'),
        ]);
        $foreignEditor = User::factory()->create([
            'email' => 'foreign-editor@example.test',
            'password' => Hash::make('password12345'),
        ]);
        $admin = User::factory()->create([
            'email' => 'admin-participant-query@example.test',
            'password' => Hash::make('password12345'),
        ]);

        app()->setCurrentConferenceId($currentConference->getKey());
        app()->setCurrentScheduledConferenceId($currentScheduledConference->getKey());

        $currentEditor->assignRole($editorRole);
        $admin->assignRole(UserRole::Admin->value);

        DB::table('model_has_roles')->insert([
            'role_id' => $editorRole->getKey(),
            'conference_id' => $foreignConference->getKey(),
            'scheduled_conference_id' => $foreignScheduledConference->getKey(),
            'model_type' => User::class,
            'model_id' => $foreignEditor->getKey(),
        ]);

        $candidateIds = (new class extends ParticipantList
        {
            public function candidateIdsForRole(int $roleId): Collection
            {
                return User::query()
                    ->where(fn (Builder $query) => $this->matchingAssignableRoleQuery($query, $roleId))
                    ->pluck('id');
            }
        })->candidateIdsForRole($editorRole->getKey());

        $this->assertTrue($candidateIds->contains($currentEditor->getKey()));
        $this->assertFalse($candidateIds->contains($foreignEditor->getKey()));
        $this->assertFalse($candidateIds->contains($admin->getKey()));
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
            'password' => 'password123456',
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $foreignUser = User::factory()->create([
            'email' => 'foreign@example.test',
            'password' => 'password123456',
        ]);
        app()->setCurrentConferenceId(999);
        app()->setCurrentScheduledConferenceId(0);
        $foreignUser->assignRole(UserRole::Author->value);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($draft->getKey());
        $this->actingAs($admin);

        $listedUserIds = UserResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($listedUserIds->contains($admin->getKey()));
        $this->assertFalse($listedUserIds->contains($foreignUser->getKey()));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_admin_role_is_available_in_scheduled_conference_scope_without_direct_permission_bypass(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Test Scheduled Conference',
            'path' => 'test-scheduled-conference',
        ]);

        Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Admin->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]);

        Permission::query()->firstOrCreate([
            'name' => 'ScheduledConference:update',
            'guard_name' => 'web',
        ]);

        $user = User::create([
            'given_name' => 'Admin',
            'family_name' => 'User',
            'email' => 'admin-scope@example.test',
            'password' => 'password123456',
        ]);

        $user->assignRole(UserRole::Admin->value);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());
        $this->actingAs($user);

        $this->assertTrue($user->fresh()->hasRole(UserRole::Admin));
        $this->assertFalse($user->fresh()->hasPermissionTo('ScheduledConference:update'));
        $this->assertTrue(Presentations::canAccess());
    }
}
