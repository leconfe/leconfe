<?php

namespace App\Actions\UserInvitation;

use App\Mail\Templates\UserRoleInvitationMail;
use App\Models\Enums\UserRole;
use App\Models\ScheduledConference;
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
        $allowedRoles = [
            UserRole::ConferenceManager->value,
            UserRole::ScheduledConferenceEditor->value,
            UserRole::TrackEditor->value,
        ];

        if (! in_array($data['role_name'], $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'role_name' => 'Selected role is not allowed for invitation.',
            ]);
        }

        $conferenceId = app()->getCurrentConferenceId();
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId();
        $roleName = $data['role_name'];

        if (! $conferenceId && $scheduledConferenceId) {
            $conferenceId = ScheduledConference::query()
                ->withoutGlobalScopes()
                ->whereKey($scheduledConferenceId)
                ->value('conference_id');
        }

        if ($roleName === UserRole::ConferenceManager->value && ! $conferenceId) {
            throw ValidationException::withMessages([
                'role_name' => 'Conference Manager invitation requires an active conference context.',
            ]);
        }

        if (in_array($roleName, [UserRole::ScheduledConferenceEditor->value, UserRole::TrackEditor->value], true) && ! $scheduledConferenceId) {
            throw ValidationException::withMessages([
                'role_name' => 'Selected role requires an active scheduled conference context.',
            ]);
        }

        $existsPendingInvitation = UserInvitation::query()
            ->where('email', $data['email'])
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

        $invitation = DB::transaction(function () use ($data, $conferenceId, $scheduledConferenceId) {
            return UserInvitation::create([
                'email' => Str::lower(trim($data['email'])),
                'role_name' => $data['role_name'],
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
