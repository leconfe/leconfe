<?php

namespace App\Actions\UserInvitation;

use App\Mail\Templates\UserRoleInvitationMail;
use App\Models\Role;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteUserAction
{
    use AsAction;

    public function handle(array $data): UserInvitation
    {
        if (! auth()->user()?->can('User:invite')) {
            throw ValidationException::withMessages([
                'email' => 'You are not allowed to invite users.',
            ]);
        }

        $email = Str::lower(trim($data['email']));
        $role = $this->resolveInvitableRole(data_get($data, 'role_id'));

        if (! $role) {
            throw ValidationException::withMessages([
                'role_id' => 'Selected role is not available in the current context.',
            ]);
        }

        // Verify the role belongs to the current context
        $conferenceId = app()->getCurrentConferenceId() ?: null;
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId() ?: null;

        if ($role->conference_id !== $conferenceId || ($role->scheduled_conference_id ?: null) !== $scheduledConferenceId) {
            throw ValidationException::withMessages([
                'role_id' => 'Selected role is not available in the current context.',
            ]);
        }

        $roleName = $role->name;

        $existsPendingInvitation = UserInvitation::query()
            ->where('email', $email)
            ->where('role_name', $roleName)
            ->where('conference_id', $conferenceId)
            ->where('scheduled_conference_id', $scheduledConferenceId)
            ->whereNull('track_id')
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($existsPendingInvitation) {
            throw ValidationException::withMessages([
                'email' => 'A pending invitation already exists for this email and role.',
            ]);
        }

        $invitation = DB::transaction(function () use ($data, $conferenceId, $scheduledConferenceId, $roleName, $email) {
            return UserInvitation::create([
                'email' => $email,
                'role_name' => $roleName,
                'conference_id' => $conferenceId,
                'scheduled_conference_id' => $scheduledConferenceId,
                'track_id' => null,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
                'status' => 'pending',
                'invited_by' => $data['invited_by'] ?? auth()->id(),
            ]);
        });

        Mail::to($invitation->email)
            ->send(new UserRoleInvitationMail($invitation));

        return $invitation;
    }

    private function resolveInvitableRole(mixed $roleId): ?Role
    {
        return Role::withoutGlobalScopes()
            ->availableRolesByContext()
            ->whereKey($roleId)
            ->first();
    }
}
