<?php

namespace App\Actions\UserInvitation;

use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class AcceptUserInvitationAction
{
    use AsAction;

    public function handle(UserInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            $conferenceId = $invitation->conference_id
                ?? $invitation->scheduledConference?->conference_id
                ?? 0;
            $scheduledConferenceId = $invitation->scheduled_conference_id ?? 0;

            $role = Role::withoutGlobalScopes()
                ->where('name', $invitation->role_name)
                ->where('conference_id', $conferenceId)
                ->where('scheduled_conference_id', $scheduledConferenceId)
                ->first();
                
            if (! $role) {
                throw ValidationException::withMessages([
                    'role_name' => 'Role is not available in invitation scope.',
                ]);
            }

            $table = config('permission.table_names.model_has_roles', 'model_has_roles');
            $rolePivotKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
            $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');

            DB::table($table)->updateOrInsert(
                [
                    $rolePivotKey => $role->getKey(),
                    'conference_id' => $conferenceId,
                    'scheduled_conference_id' => $scheduledConferenceId,
                    'model_type' => User::class,
                    $modelMorphKey => $user->getKey(),
                ],
                []
            );

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        });
    }
}
