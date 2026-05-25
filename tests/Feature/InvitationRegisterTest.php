<?php

namespace Tests\Feature;

use App\Frontend\Website\Pages\InvitationRegister;
use App\Mail\Templates\VerifyUserEmail;
use App\Models\Conference;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_registration_keeps_new_user_unverified_and_sends_verification_email(): void
    {
        Config::set('app.must_verify_email', true);
        Mail::fake();

        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $this->createInvitationRole($conference);

        $invitation = UserInvitation::query()->create([
            'email' => 'invitee@example.com',
            'role_name' => 'Reviewer',
            'token' => 'invite-token-new-user',
            'status' => 'pending',
            'conference_id' => $conference->id,
        ]);

        Livewire::test(InvitationRegister::class, ['token' => $invitation->token])
            ->set('given_name', 'Invitee')
            ->set('family_name', 'User')
            ->set('password', 'password12345')
            ->set('password_confirmation', 'password12345')
            ->set('privacy_statement_agree', true)
            ->call('register');

        $user = User::query()
            ->where('email', 'invitee@example.com')
            ->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
        Mail::assertQueued(VerifyUserEmail::class, 1);
    }

    public function test_invitation_registration_rejects_existing_user_email(): void
    {
        Config::set('app.must_verify_email', true);
        Mail::fake();

        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $this->createInvitationRole($conference);

        $user = User::query()->create([
            'given_name' => 'Existing',
            'family_name' => 'User',
            'email' => 'existing@example.com',
            'password' => 'password12345',
        ]);

        $invitation = UserInvitation::query()->create([
            'email' => $user->email,
            'role_name' => 'Reviewer',
            'token' => 'invite-token-existing-user',
            'status' => 'pending',
            'conference_id' => $conference->id,
        ]);

        Livewire::test(InvitationRegister::class, ['token' => $invitation->token])
            ->set('given_name', 'Ignored')
            ->set('family_name', 'Ignored')
            ->set('password', 'password12345')
            ->set('password_confirmation', 'password12345')
            ->set('privacy_statement_agree', true)
            ->call('register')
            ->assertHasErrors(['token']);

        $user->refresh();

        $this->assertNull($user->email_verified_at);
        $this->assertGuest();
        $this->assertDatabaseHas('user_invitations', [
            'id' => $invitation->id,
            'status' => 'pending',
        ]);
        Mail::assertNothingQueued();
    }

    protected function createInvitationRole(Conference $conference): void
    {
        Role::query()->create([
            'name' => 'Reviewer',
            'conference_id' => $conference->id,
            'guard_name' => 'web',
        ]);
    }
}
