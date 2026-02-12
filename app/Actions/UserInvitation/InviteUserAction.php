<?php

namespace App\Actions\UserInvitation;

use App\Mail\Templates\UserRoleInvitationMail;
use App\Models\Enums\UserRole;
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
        $roleId = data_get($data, 'role_id');
        $role = Role::query()->whereKey($roleId)->first();
        $allowedRoleNames = collect(UserRole::internalRoles())
            ->map(fn (UserRole $role) => $role->value)
            ->reject(fn (string $roleName) => $roleName === UserRole::Admin->value)
            ->values()
            ->toArray();

        if (! $role || ! in_array($role->name, $allowedRoleNames, true)) {
            throw ValidationException::withMessages([
                'role_id' => 'Selected role is not allowed for invitation.',
            ]);
        }

        $conferenceId = $role->conference_id ?: null;
        $scheduledConferenceId = $role->scheduled_conference_id ?: null;
        $roleName = $role->name;

        $existsPendingInvitation = UserInvitation::query()
            ->where('email', $email)
            ->where('role_name', $roleName)
            ->where('conference_id', $conferenceId)
            ->where('scheduled_conference_id', $scheduledConferenceId)
            ->whereNull('track_id')
            ->where('status', 'pending')
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
}
