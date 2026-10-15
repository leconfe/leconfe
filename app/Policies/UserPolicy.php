<?php

namespace App\Policies;

use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Support\UserBanScope;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        if ($user->can('User:viewAny')) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model)
    {
        if ($user->can('User:view')) {
            return true;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        if ($user->can('User:create')) {
            return true;
        }
    }

    public function invite(User $user)
    {
        if ($user->can('User:invite')) {
            return true;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model)
    {
        if ($user->is($model)) {
            return true;
        }

        if ($model->hasRole(UserRole::Admin)) {
            return false;
        }

        if ($user->can('User:update')) {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model)
    {
        if ($user->is($model)) {
            return false;
        }

        if ($model->hasRole(UserRole::Admin)) {
            return false;
        }

        if ($user->can('User:delete')) {
            return true;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model)
    {
        if ($user->can('User:restore')) {
            return true;
        }
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model)
    {
        if ($user->can('User:forceDelete')) {
            return true;
        }
    }

    public function loginAs(User $user, User $model)
    {
        if (! $model->canBeImpersonated()) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        if ($user->can('User:loginAs')) {
            return true;
        }
    }

    public function disable(User $user, User $model)
    {
        if ($model->isBannedInCurrentContext()) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        // Explicitly dont allow disabling admin users
        if ($model->hasAnyRole([UserRole::Admin->value])) {
            return false;
        }

        return $this->canManageBan($user, $model, 'User:disable');
    }

    public function enable(User $user, User $model)
    {
        if (! $model->hasBanInCurrentScope()) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        return $this->canManageBan($user, $model, 'User:enable');
    }

    private function canManageBan(User $user, User $model, string $permission): bool
    {
        $scope = UserBanScope::current();
        $assignmentsTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        if (! $scope->isValid()) {
            return false;
        }

        // Query assignments afresh so roles loaded in another context cannot grant access.
        $isAdmin = $user->roles()->withoutGlobalScopes()
            ->where('name', UserRole::Admin->value)
            ->where('roles.conference_id', 0)
            ->where('roles.scheduled_conference_id', 0)
            ->wherePivot('conference_id', 0)
            ->wherePivot('scheduled_conference_id', 0)
            ->exists();

        if ($isAdmin) {
            return true;
        }

        if ($scope->isGlobal() || $user->isBannedInCurrentContext()) {
            return false;
        }

        $memberQuery = $model->roles()->withoutGlobalScopes()
            ->where('roles.conference_id', $scope->conferenceId)
            ->whereColumn('roles.conference_id', $assignmentsTable.'.conference_id')
            ->whereColumn('roles.scheduled_conference_id', $assignmentsTable.'.scheduled_conference_id');

        if ($scope->scheduledConferenceId) {
            $memberQuery->where('roles.scheduled_conference_id', $scope->scheduledConferenceId)
                ->wherePivot('scheduled_conference_id', $scope->scheduledConferenceId);
        }

        if (! $memberQuery->exists()) {
            return false;
        }

        $roles = $user->roles()->withoutGlobalScopes()
            ->with(['meta', 'permissions'])
            ->where('roles.conference_id', $scope->conferenceId)
            ->whereColumn('roles.conference_id', $assignmentsTable.'.conference_id')
            ->whereColumn('roles.scheduled_conference_id', $assignmentsTable.'.scheduled_conference_id')
            ->whereIn('roles.scheduled_conference_id', [0, $scope->scheduledConferenceId ?? 0])
            ->get();

        return $roles->contains(fn (Role $role) => in_array(
            $permission,
            Role::getPermissionsForRole($role->getMeta('permission_level') ?? $role->name),
        ) || $role->permissions->contains('name', $permission))
            || ($roles->isNotEmpty() && $user->permissions()->where('name', $permission)->exists());
    }

    public function sendEmail(User $user, User $model)
    {
        if ($user->can('User:sendEmail')) {
            return true;
        }
    }

    public function assignPermissions(User $user)
    {
        if ($user->can('User:assignPermissions')) {
            return true;
        }
    }

    public function accessAdministration(User $user)
    {
        if ($user->can('User:accessAdministration')) {
            return true;
        }
    }
}
