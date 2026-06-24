<?php

namespace Tests\Feature;

use App\Actions\UserInvitation\InviteUserAction;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InviteUserActionTest extends TestCase
{
    use RefreshDatabase;

    protected Conference $conference;
    protected Conference $otherConference;
    protected Role $role;
    protected Role $otherRole;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = Conference::query()->create([
            'name' => 'NAL Conference',
            'path' => 'nal-conference',
        ]);

        $this->otherConference = Conference::query()->create([
            'name' => 'Al-Hikmah Education Conference',
            'path' => 'al-hikmah',
        ]);

        $this->role = Role::query()->create([
            'name' => 'Author',
            'conference_id' => $this->conference->getKey(),
            'guard_name' => 'web',
        ]);

        $this->otherRole = Role::query()->create([
            'name' => 'Author',
            'conference_id' => $this->otherConference->getKey(),
            'guard_name' => 'web',
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@nal-conference.test',
            'password' => Hash::make('password'),
        ]);

        $this->createAdminRole();

         $this->admin->assignRole(UserRole::Admin->value);
     }

    protected function createAdminRole(): void
    {
        Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Admin->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]);
    }

    public function test_invitation_uses_current_conference_context_not_role_context(): void
    {
        Mail::fake();

        app()->setCurrentConferenceId($this->conference->getKey());
        $this->actingAs($this->admin);

        $invitation = InviteUserAction::run([
            'email' => 'author@example.test',
            'role_id' => $this->role->getKey(),
        ]);

        $this->assertInstanceOf(UserInvitation::class, $invitation);
        $this->assertSame($this->conference->getKey(), $invitation->conference_id);
        $this->assertNull($invitation->scheduled_conference_id);
        $this->assertSame('Author', $invitation->role_name);
        $this->assertSame('author@example.test', $invitation->email);
        $this->assertSame('pending', $invitation->status);
    }

    public function test_invitation_rejects_role_from_different_conference(): void
    {
        Mail::fake();

        app()->setCurrentConferenceId($this->conference->getKey());
        $this->actingAs($this->admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Selected role is not available in the current context.');

        InviteUserAction::run([
            'email' => 'author@example.test',
            'role_id' => $this->otherRole->getKey(),
        ]);
    }

    public function test_invitation_prevents_duplicate_pending_invitation(): void
    {
        Mail::fake();

        app()->setCurrentConferenceId($this->conference->getKey());
        $this->actingAs($this->admin);

        InviteUserAction::run([
            'email' => 'author@example.test',
            'role_id' => $this->role->getKey(),
        ]);

        $this->assertDatabaseCount('user_invitations', 1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A pending invitation already exists for this email and role.');

        InviteUserAction::run([
            'email' => 'author@example.test',
            'role_id' => $this->role->getKey(),
        ]);
    }

    public function test_invitation_allows_same_email_in_different_conference(): void
    {
        Mail::fake();

        // First invitation in NAL conference
        app()->setCurrentConferenceId($this->conference->getKey());
        $this->actingAs($this->admin);

        InviteUserAction::run([
            'email' => 'author@example.test',
            'role_id' => $this->role->getKey(),
        ]);

        // Switch to other conference
        $otherAdmin = User::factory()->create([
            'email' => 'admin@alhikmah.test',
            'password' => Hash::make('password'),
        ]);
        $otherAdmin->assignRole(UserRole::Admin->value);

        app()->setCurrentConferenceId($this->otherConference->getKey());
        $this->actingAs($otherAdmin);

        // Same email, different conference — should succeed
        $invitation = InviteUserAction::run([
            'email' => 'author@example.test',
            'role_id' => $this->otherRole->getKey(),
        ]);

        $this->assertSame($this->otherConference->getKey(), $invitation->conference_id);
        $this->assertDatabaseCount('user_invitations', 2);
    }
}
