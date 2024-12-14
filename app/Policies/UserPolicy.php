<?php

namespace App\Policies;

use App\Models\Enums\UserRole;
use App\Models\User;

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

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model)
    {
        // User can delete themselves
        if ($user->is($model)) {
            return true;
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
        // User can delete themselves
        if ($user->is($model)) {
            return true;
        }

        if($model->hasRole(UserRole::Admin)){
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
        if ($model->isBanned()) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        // Explicitly dont allow disabling admin users
        if ($model->hasAnyRole([UserRole::Admin->value])) {
            return false;
        }

        if ($user->can('User:disable')) {
            return true;
        }
    }

    public function enable(User $user, User $model)
    {
        if (! $model->isBanned()) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        if ($user->can('User:enable')) {
            return true;
        }
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
