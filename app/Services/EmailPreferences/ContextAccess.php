<?php

namespace App\Services\EmailPreferences;

use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\ScheduledConference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContextAccess
{
    /**
     * Scheduled conferences the user may configure, keyed by
     * "{conference_id}:{scheduled_conference_id}".
     *
     * Site admins see every scheduled conference, conference managers see every
     * scheduled conference of their conference, other users only the scheduled
     * conferences they hold a role in.
     *
     * @return array<string, string>
     */
    public function contexts(User $user): array
    {
        $memberships = $this->memberships($user);
        $isAdmin = $memberships->contains(fn ($role) => $role->name === UserRole::Admin->value && (int) $role->conference_id === 0);

        $conferences = Conference::withoutGlobalScopes()
            ->when(! $isAdmin, fn ($query) => $query->whereIn('id', $memberships->pluck('conference_id')->filter()))
            ->orderBy('name')
            ->get();

        $contexts = [];

        foreach ($conferences as $conference) {
            $roles = $memberships->where('conference_id', $conference->getKey());
            $isManager = $roles->contains(fn ($role) => $role->name === UserRole::ConferenceManager->value && (int) $role->scheduled_conference_id === 0);

            ScheduledConference::withoutGlobalScopes()
                ->where('conference_id', $conference->getKey())
                ->whereNull('deleted_at')
                ->when(! $isAdmin && ! $isManager, fn ($query) => $query->whereIn('id', $roles->pluck('scheduled_conference_id')))
                ->orderBy('title')
                ->get()
                ->each(function (ScheduledConference $scheduledConference) use ($conference, &$contexts) {
                    $contexts[$conference->getKey().':'.$scheduledConference->getKey()] = $conference->name.' / '.$scheduledConference->title;
                });
        }

        return $contexts;
    }

    public function allows(User $user, int $conferenceId, int $scheduledConferenceId): bool
    {
        return $conferenceId > 0
            && $scheduledConferenceId > 0
            && array_key_exists($conferenceId.':'.$scheduledConferenceId, $this->contexts($user));
    }

    /**
     * Role assignments whose pivot scope matches the role scope.
     */
    protected function memberships(User $user): Collection
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        return DB::table("{$rolesTable} as r")
            ->join("{$modelHasRolesTable} as p", 'p.role_id', '=', 'r.id')
            ->where('p.model_id', $user->getKey())
            ->where('p.model_type', $user->getMorphClass())
            ->whereColumn('p.conference_id', 'r.conference_id')
            ->whereColumn('p.scheduled_conference_id', 'r.scheduled_conference_id')
            ->select('r.conference_id', 'r.scheduled_conference_id', 'r.name')
            ->get();
    }
}
